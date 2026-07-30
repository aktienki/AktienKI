#!/usr/bin/env python3
"""Lokale PostgreSQL-Datenbank sicher über SSH auf einen Server übertragen."""

from __future__ import annotations

import argparse
import getpass
import os
import shutil
import socket
import subprocess
import sys
import time
from datetime import datetime
from pathlib import Path


def require_program(name: str) -> None:
    if shutil.which(name) is None:
        raise SystemExit(
            f"Fehlt: {name}. Installiere die PostgreSQL-Werkzeuge auf dem Mac, "
            "z. B. mit: brew install libpq && brew link --force libpq"
        )


def password_environment(password: str) -> dict[str, str]:
    environment = os.environ.copy()
    if password:
        environment["PGPASSWORD"] = password
    else:
        environment.pop("PGPASSWORD", None)
    return environment


def wait_for_port(host: str, port: int, tunnel: subprocess.Popen[bytes]) -> None:
    for _ in range(40):
        if tunnel.poll() is not None:
            raise SystemExit("Der SSH-Tunnel konnte nicht aufgebaut werden.")
        try:
            with socket.create_connection((host, port), timeout=0.25):
                return
        except OSError:
            time.sleep(0.25)
    raise SystemExit(f"Der SSH-Tunnel auf Port {port} antwortet nicht.")


def run(command: list[str], password: str, description: str) -> None:
    print(f"\n→ {description}")
    result = subprocess.run(command, env=password_environment(password))
    if result.returncode != 0:
        raise SystemExit(f"Fehler bei: {description}")


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Lokale PostgreSQL-Datenbank über einen SSH-Tunnel übertragen."
    )
    parser.add_argument("--local-db", required=True, help="Name der lokalen Datenbank")
    parser.add_argument("--local-user", required=True, help="Lokaler PostgreSQL-Benutzer")
    parser.add_argument("--local-host", default="127.0.0.1")
    parser.add_argument("--local-port", type=int, default=5432)
    parser.add_argument("--remote-db", default="aktienki")
    parser.add_argument("--remote-user", default="aktienki_app")
    parser.add_argument("--server", default="217.154.240.14")
    parser.add_argument("--ssh-user", default="root")
    parser.add_argument(
        "--ssh-key",
        default="~/.ssh/aktienKI_SSH",
        help="Privater SSH-Schlüssel (niemals die .pub-Datei)",
    )
    parser.add_argument("--tunnel-port", type=int, default=15432)
    return parser.parse_args()


def main() -> None:
    args = parse_arguments()
    for program in ("ssh", "pg_dump", "pg_restore"):
        require_program(program)

    ssh_key = Path(args.ssh_key).expanduser()
    if not ssh_key.is_file():
        raise SystemExit(f"SSH-Schlüssel nicht gefunden: {ssh_key}")

    timestamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    backup_folder = Path.home() / "Desktop" / "Postgres-Backups"
    backup_folder.mkdir(parents=True, exist_ok=True)
    local_dump = backup_folder / f"{args.local_db}-lokal-{timestamp}.dump"
    remote_backup = backup_folder / f"{args.remote_db}-server-vorher-{timestamp}.dump"

    local_password = getpass.getpass(
        f"Passwort für lokale Rolle {args.local_user} (Enter, falls keines): "
    )
    remote_password = getpass.getpass(
        f"Passwort für Server-Rolle {args.remote_user}: "
    )

    tunnel_command = [
        "ssh",
        "-i",
        str(ssh_key),
        "-N",
        "-L",
        f"{args.tunnel_port}:127.0.0.1:5432",
        "-o",
        "ExitOnForwardFailure=yes",
        "-o",
        "ServerAliveInterval=30",
        f"{args.ssh_user}@{args.server}",
    ]

    print("\n→ SSH-Tunnel wird geöffnet. Falls gefragt, SSH-Passphrase eingeben.")
    tunnel = subprocess.Popen(tunnel_command)
    try:
        wait_for_port("127.0.0.1", args.tunnel_port, tunnel)
        print("✓ SSH-Tunnel steht.")

        run(
            [
                "pg_dump",
                "-h",
                args.local_host,
                "-p",
                str(args.local_port),
                "-U",
                args.local_user,
                "-d",
                args.local_db,
                "-Fc",
                "-f",
                str(local_dump),
            ],
            local_password,
            "Lokale Datenbank exportieren",
        )

        run(
            [
                "pg_dump",
                "-h",
                "127.0.0.1",
                "-p",
                str(args.tunnel_port),
                "-U",
                args.remote_user,
                "-d",
                args.remote_db,
                "-Fc",
                "-f",
                str(remote_backup),
            ],
            remote_password,
            "Sicherung der aktuellen Server-Datenbank erstellen",
        )

        print(f"\nLokaler Export: {local_dump}")
        print(f"Server-Sicherung: {remote_backup}")
        print(
            "\nACHTUNG: Der Inhalt der Server-Datenbank wird jetzt durch "
            "die lokale Datenbank ersetzt."
        )
        if input("Zum Fortfahren exakt JA eingeben: ").strip() != "JA":
            raise SystemExit("Abgebrochen. Es wurden keine Server-Daten verändert.")

        run(
            [
                "pg_restore",
                "-h",
                "127.0.0.1",
                "-p",
                str(args.tunnel_port),
                "-U",
                args.remote_user,
                "-d",
                args.remote_db,
                "--clean",
                "--if-exists",
                "--no-owner",
                "--no-privileges",
                "--exit-on-error",
                str(local_dump),
            ],
            remote_password,
            "Lokale Datenbank auf dem Server wiederherstellen",
        )
        print("\n✓ Übertragung erfolgreich abgeschlossen.")
    finally:
        if tunnel.poll() is None:
            tunnel.terminate()
            try:
                tunnel.wait(timeout=5)
            except subprocess.TimeoutExpired:
                tunnel.kill()


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\nAbgebrochen.")
        sys.exit(130)
