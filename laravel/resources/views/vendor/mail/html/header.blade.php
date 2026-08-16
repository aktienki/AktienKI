@props(['url'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#0b192b;border-bottom:1px solid #29475e">
    <tr>
        <td style="padding:17px 22px">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td valign="middle" style="width:47%">
                        <a href="{{ $url }}" style="display:inline-block;text-decoration:none">
                            <img src="cid:aktienki-logo@aktienki.com" width="180" height="75" alt="aktienKI.com" style="display:block;width:180px;height:75px;object-fit:contain;border:0">
                        </a>
                    </td>
                    <td align="right" valign="middle" style="padding-left:16px">
                        <div style="color:#64e5f2;font-size:10px;line-height:1.2;font-weight:800;letter-spacing:1.7px;text-transform:uppercase">{{ __('Datenbasierte Aktienanalyse') }}</div>
                        <div style="margin-top:5px;color:#a9bac9;font-size:11px;line-height:1.35">{{ __('Maschinelles Lernen · Klare Signale') }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
