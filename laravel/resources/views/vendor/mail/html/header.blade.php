@props(['url'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#0b192b;border-bottom:1px solid #29475e">
    <tr>
        <td style="padding:17px 22px">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td valign="middle" style="width:52%">
                        <a href="{{ $url }}" style="display:inline-block;text-decoration:none">
                            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                                <td valign="middle"><img src="cid:aktienki-logo@aktienki.com" width="48" height="48" alt="" style="display:block;width:48px;height:48px;object-fit:contain;border:0"></td>
                                <td valign="middle" style="padding-left:10px;color:#ffffff;font-size:21px;line-height:1;font-weight:900;letter-spacing:.2px;white-space:nowrap">aktienKI<span style="color:#ffffff">.com</span></td>
                            </tr></table>
                        </a>
                    </td>
                    <td align="right" valign="middle" style="padding-left:16px">
                        <div style="color:#ffffff;font-size:10px;line-height:1.2;font-weight:800;letter-spacing:1.7px;text-transform:uppercase">{{ __('Datenbasierte Aktienanalyse') }}</div>
                        <div style="margin-top:5px;color:#a9bac9;font-size:11px;line-height:1.35">{{ __('Maschinelles Lernen · Klare Signale') }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
