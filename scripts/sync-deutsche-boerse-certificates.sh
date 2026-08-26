#!/usr/bin/env bash

set -Eeuo pipefail

readonly SOURCE_URL="${CERTIFICATES_SOURCE_URL:-https://www.cashmarket.deutsche-boerse.com/resource/blob/1790218/2d87a13e289870e0543b89604214ce60/data/t7-xfra-BFZ-allTradableInstruments.zip}"
readonly TARGET_DIR="${CERTIFICATES_TARGET_DIR:-/home/aktienki/AktienKI/storage/imports/deutsche-boerse}"
readonly TARGET_FILE="${TARGET_DIR}/t7-xfra-BFZ-allTradableInstruments-current.zip"
readonly METADATA_FILE="${TARGET_DIR}/t7-xfra-BFZ-allTradableInstruments-current.json"

mkdir -p "${TARGET_DIR}"

temporary_file="$(mktemp "${TARGET_DIR}/.certificates.XXXXXX.part")"
temporary_metadata="$(mktemp "${TARGET_DIR}/.certificates.XXXXXX.json")"

cleanup() {
    rm -f "${temporary_file}" "${temporary_metadata}"
}
trap cleanup EXIT

curl \
    --fail \
    --location \
    --silent \
    --show-error \
    --retry 4 \
    --retry-all-errors \
    --connect-timeout 30 \
    --max-time 1800 \
    --output "${temporary_file}" \
    "${SOURCE_URL}"

# Reject HTML error pages and incomplete archives before touching the current file.
unzip -tqq "${temporary_file}"

csv_entry="$(unzip -Z1 "${temporary_file}" | awk 'BEGIN { IGNORECASE=1 } /\.csv$/ { print; exit }')"
if [[ -z "${csv_entry}" ]]; then
    echo "The downloaded archive contains no CSV file." >&2
    exit 1
fi

archive_size="$(stat -c '%s' "${temporary_file}")"
if (( archive_size < 1000000 )); then
    echo "The downloaded archive is unexpectedly small (${archive_size} bytes)." >&2
    exit 1
fi

archive_hash="$(sha256sum "${temporary_file}" | awk '{print $1}')"
downloaded_at="$(date --iso-8601=seconds)"

printf '{\n  "source_url": "%s",\n  "downloaded_at": "%s",\n  "archive_bytes": %s,\n  "sha256": "%s",\n  "csv_entry": "%s"\n}\n' \
    "${SOURCE_URL}" \
    "${downloaded_at}" \
    "${archive_size}" \
    "${archive_hash}" \
    "${csv_entry//\"/\\\"}" > "${temporary_metadata}"

# Both files live on the same filesystem, so rename is atomic. A failed download
# therefore leaves yesterday's verified archive untouched.
chmod 0640 "${temporary_file}" "${temporary_metadata}"
mv -f "${temporary_file}" "${TARGET_FILE}"
mv -f "${temporary_metadata}" "${METADATA_FILE}"

# Remove superseded dated/manual downloads only after the new archive is valid.
find "${TARGET_DIR}" -maxdepth 1 -type f \
    -name 't7-xfra-BFZ-allTradableInstruments-*.zip' \
    ! -name 't7-xfra-BFZ-allTradableInstruments-current.zip' \
    -delete

echo "Certificate archive updated: ${TARGET_FILE} (${archive_size} bytes, ${csv_entry})"
