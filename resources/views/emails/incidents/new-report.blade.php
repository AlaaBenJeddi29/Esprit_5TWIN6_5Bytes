<!DOCTYPE html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>Nouveau signalement d'incident — AquaSecure</title>

    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->

    <style type="text/css">
        body, table, td, a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0;
            mso-table-rspace: 0;
        }
        table {
            border-collapse: collapse !important;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        body {
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        a[x-apple-data-detectors] {
            color: inherit !important;
            font-family: inherit !important;
            font-size: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
            text-decoration: none !important;
        }
        @media screen and (max-width: 620px) {
            .email-container {
                width: 100% !important;
            }
            .mobile-padding {
                padding-left: 22px !important;
                padding-right: 22px !important;
            }
            .mobile-title {
                font-size: 26px !important;
                line-height: 33px !important;
            }
            .summary-cell {
                display: block !important;
                width: 100% !important;
                border-right: 0 !important;
                border-bottom: 1px solid #e4e7ec !important;
                box-sizing: border-box !important;
            }
            .summary-cell-last {
                border-bottom: 0 !important;
            }
            .stack {
                display: block !important;
                width: 100% !important;
            }
            .mobile-space {
                height: 12px !important;
            }
            .mobile-center {
                text-align: center !important;
            }
            .mobile-button {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #eef2f7; color: #111928; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.55;">

@php
    $severityTones = [
        'faible' => ['text' => '#067647', 'background' => '#ecfdf3', 'border' => '#abefc6'],
        'moyenne' => ['text' => '#b54708', 'background' => '#fffaeb', 'border' => '#fedf89'],
        'haute' => ['text' => '#c4320a', 'background' => '#fff6ed', 'border' => '#f9dbaf'],
        'critique' => ['text' => '#b42318', 'background' => '#fef3f2', 'border' => '#fecdca'],
    ];
    $severityTone = $severityTones[$incident->severity] ?? ['text' => '#475467', 'background' => '#f2f4f7', 'border' => '#e4e7ec'];
    $submittedLabel = $submittedAt?->copy()->locale('fr')->translatedFormat('d F Y à H:i') ?? 'Non renseignée';
    $citizenName = filled($citizen?->name) ? $citizen->name : 'Citoyen non identifié';
    $nameParts = preg_split('/\s+/u', trim($citizenName)) ?: [];
    $initials = mb_strtoupper(
        mb_substr($nameParts[0] ?? 'C', 0, 1).mb_substr($nameParts[1] ?? '', 0, 1)
    );
    $description = filled($incident->description) ? $incident->description : 'Aucune description fournie.';
@endphp

<div style="display: none; max-height: 0; max-width: 0; overflow: hidden; opacity: 0; mso-hide: all; font-size: 1px; line-height: 1px; color: #eef2f7;">
    Nouveau signalement {{ $incident->reference }} — {{ $incident->title }}
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; background-color: #eef2f7;">
    <tr>
        <td align="center" style="padding: 32px 12px;">
            <table role="presentation" class="email-container" width="640" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 640px; background-color: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 18px 45px rgba(17, 25, 40, 0.12);">

                <tr>
                    <td class="mobile-padding" style="padding: 30px 34px 34px; background-color: #3758f9; background-image: linear-gradient(135deg, #3758f9 0%, #1b44c8 100%);">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td class="stack" valign="middle">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td width="48" height="48" align="center" valign="middle" style="width: 48px; height: 48px; background-color: #ffffff; border-radius: 13px; padding: 5px;">
                                                <img src="{{ asset('front/images/favicon.png') }}" width="38" height="37" alt="AquaSecure" style="display: block; width: 38px; max-width: 38px; height: auto;">
                                            </td>
                                            <td style="padding-left: 12px; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: -0.3px;">
                                                AquaSecure
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td class="stack mobile-center" align="right" valign="middle" style="padding-top: 14px;">
                                    <span style="display: inline-block; padding: 7px 12px; border-radius: 999px; background-color: #ffffff; color: #1b44c8; font-size: 10px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase;">
                                        Nouveau signalement
                                    </span>
                                </td>
                            </tr>
                        </table>

                        <h1 class="mobile-title" style="margin: 26px 0 10px; color: #ffffff; font-size: 31px; line-height: 39px; font-weight: 700; letter-spacing: -0.6px;">
                            Nouveau signalement d'incident
                        </h1>
                        <p style="margin: 0; color: #dde7ff; font-size: 15px; line-height: 24px;">
                            Un citoyen vient de soumettre un signalement via AquaSecure. Les informations enregistrées sont disponibles ci-dessous pour examen.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td class="mobile-padding" style="padding: 34px 34px 10px;">
                        <p style="margin: 0 0 9px; color: #111928; font-size: 16px; line-height: 25px; font-weight: 600;">
                            Bonjour,
                        </p>
                        <p style="margin: 0; color: #637381; font-size: 14px; line-height: 23px;">
                            Un nouveau dossier a été enregistré et attend votre consultation dans le Back Office.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin-top: 24px; border: 1px solid #e4e7ec; border-radius: 12px; border-collapse: separate; overflow: hidden;">
                            <tr>
                                <td class="summary-cell" width="42%" valign="top" style="padding: 16px 18px; background-color: #f9fafb; border-right: 1px solid #e4e7ec;">
                                    <span style="display: block; color: #667085; font-size: 10px; line-height: 15px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">Référence</span>
                                    <span style="display: block; margin-top: 5px; color: #111928; font-size: 17px; line-height: 24px; font-weight: 700;">{{ $incident->reference }}</span>
                                </td>
                                <td class="summary-cell" width="26%" valign="top" style="padding: 16px 18px; border-right: 1px solid #e4e7ec;">
                                    <span style="display: block; color: #667085; font-size: 10px; line-height: 15px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">Gravité</span>
                                    <span style="display: inline-block; margin-top: 6px; padding: 4px 9px; border: 1px solid {{ $severityTone['border'] }}; border-radius: 999px; background-color: {{ $severityTone['background'] }}; color: {{ $severityTone['text'] }}; font-size: 12px; line-height: 18px; font-weight: 700;">
                                        {{ $incident->severityLabel() }}
                                    </span>
                                </td>
                                <td class="summary-cell summary-cell-last" width="32%" valign="top" style="padding: 16px 18px;">
                                    <span style="display: block; color: #667085; font-size: 10px; line-height: 15px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">Reçu le</span>
                                    <span style="display: block; margin-top: 5px; color: #344054; font-size: 13px; line-height: 20px; font-weight: 600;">{{ $submittedLabel }}</span>
                                </td>
                            </tr>
                        </table>

                        <h2 style="margin: 30px 0 12px; color: #111928; font-size: 17px; line-height: 25px; font-weight: 700;">
                            Citoyen à l'origine du signalement
                        </h2>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; background-color: #f8faff; border: 1px solid #e4e7ec; border-radius: 12px; border-collapse: separate;">
                            <tr>
                                <td width="56" valign="top" style="width: 56px; padding: 16px 0 16px 16px;">
                                    <div style="width: 42px; height: 42px; line-height: 42px; border-radius: 21px; background-color: #3758f9; color: #ffffff; text-align: center; font-size: 14px; font-weight: 700;">
                                        {{ $initials }}
                                    </div>
                                </td>
                                <td valign="top" style="padding: 16px;">
                                    <div style="color: #111928; font-size: 15px; line-height: 22px; font-weight: 700;">{{ $citizenName }}</div>
                                    @if (filled($citizen?->email))
                                        <a href="mailto:{{ $citizen->email }}" style="display: inline-block; margin-top: 3px; color: #3758f9; font-size: 13px; line-height: 20px; text-decoration: none; word-break: break-word;">{{ $citizen->email }}</a>
                                    @else
                                        <div style="margin-top: 3px; color: #667085; font-size: 13px; line-height: 20px;">Adresse e-mail non disponible</div>
                                    @endif
                                </td>
                            </tr>
                        </table>

                        <h2 style="margin: 30px 0 12px; color: #111928; font-size: 17px; line-height: 25px; font-weight: 700;">
                            Détails du signalement
                        </h2>

                        <div style="padding: 20px; background-color: #ffffff; border: 1px solid #e4e7ec; border-radius: 12px;">
                            <div style="color: #667085; font-size: 10px; line-height: 15px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">Objet</div>
                            <div style="margin-top: 6px; color: #111928; font-size: 20px; line-height: 29px; font-weight: 700; letter-spacing: -0.2px; word-break: break-word;">
                                {{ $incident->title }}
                            </div>
                            <div style="margin-top: 7px;">
                                <span style="display: inline-block; padding: 4px 9px; border-radius: 999px; background-color: #eef2ff; color: #1b44c8; font-size: 11px; line-height: 17px; font-weight: 700;">
                                    {{ $incident->typeLabel() }}
                                </span>
                            </div>
                        </div>

                        <div style="margin-top: 18px; padding: 18px 20px; background-color: #f9fafb; border-left: 4px solid #3758f9; border-radius: 8px;">
                            <div style="color: #667085; font-size: 10px; line-height: 15px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">Description transmise</div>
                            <div style="margin-top: 8px; color: #344054; font-size: 14px; line-height: 23px; word-break: break-word;">
                                {!! nl2br(e($description)) !!}
                            </div>
                        </div>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin-top: 22px;">
                            <tr>
                                <td width="50%" valign="top" style="width: 50%; padding: 0 10px 0 0;">
                                    <div style="padding: 14px 0; border-bottom: 1px solid #f2f4f7;">
                                        <div style="color: #667085; font-size: 10px; line-height: 15px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase;">Statut initial</div>
                                        <div style="margin-top: 5px;">
                                            <span style="display: inline-block; padding: 4px 9px; border-radius: 999px; background-color: #fffaeb; color: #b54708; font-size: 12px; line-height: 18px; font-weight: 700;">
                                                {{ $incident->statusLabel() }}
                                            </span>
                                        </div>
                                    </div>
                                    <div style="padding: 14px 0; border-bottom: 1px solid #f2f4f7;">
                                        <div style="color: #667085; font-size: 10px; line-height: 15px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase;">Priorité</div>
                                        <div style="margin-top: 4px; color: #344054; font-size: 14px; line-height: 22px; font-weight: 600;">{{ $incident->priorityLabel() }}</div>
                                    </div>
                                    <div style="padding: 14px 0;">
                                        <div style="color: #667085; font-size: 10px; line-height: 15px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase;">Zone</div>
                                        <div style="margin-top: 4px; color: #344054; font-size: 14px; line-height: 22px; font-weight: 600;">{{ $incident->zone?->nom ?? 'Non renseignée' }}</div>
                                    </div>
                                </td>
                                <td width="50%" valign="top" style="width: 50%; padding: 0 0 0 10px;">
                                    <div style="padding: 14px 0; border-bottom: 1px solid #f2f4f7;">
                                        <div style="color: #667085; font-size: 10px; line-height: 15px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase;">Infrastructure</div>
                                        <div style="margin-top: 4px; color: #344054; font-size: 14px; line-height: 22px; font-weight: 600;">{{ $incident->infrastructure?->nom ?? 'Non renseignée' }}</div>
                                    </div>
                                    <div style="padding: 14px 0; border-bottom: 1px solid #f2f4f7;">
                                        <div style="color: #667085; font-size: 10px; line-height: 15px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase;">Adresse / repère</div>
                                        <div style="margin-top: 4px; color: #344054; font-size: 14px; line-height: 22px; font-weight: 600; word-break: break-word;">{{ $incident->address ?? 'Non renseignée' }}</div>
                                    </div>
                                    <div style="padding: 14px 0;">
                                        <div style="color: #667085; font-size: 10px; line-height: 15px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase;">Coordonnées GPS</div>
                                        <div style="margin-top: 4px; color: #344054; font-size: 14px; line-height: 22px; font-weight: 600;">
                                            @if ($incident->latitude !== null && $incident->longitude !== null)
                                                {{ number_format((float) $incident->latitude, 5, ',', ' ') }} — {{ number_format((float) $incident->longitude, 5, ',', ' ') }}
                                            @else
                                                Non renseignées
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        @if ($incident->is_duplicate)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin-top: 20px; background-color: #fffaeb; border: 1px solid #fedf89; border-radius: 10px; border-collapse: separate;">
                                <tr>
                                    <td style="padding: 14px 16px; color: #b54708; font-size: 13px; line-height: 21px;">
                                        <strong>Doublon probable détecté.</strong>
                                        Ce signalement pourrait correspondre à
                                        <strong>{{ $incident->duplicateOf?->reference ?? 'un incident existant' }}</strong>.
                                    </td>
                                </tr>
                            </table>
                        @endif
                    </td>
                </tr>

                <tr>
                    <td class="mobile-padding" style="padding: 14px 34px 34px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; background-color: #f2f6ff; border: 1px solid #c2d6ff; border-radius: 14px; border-collapse: separate;">
                            <tr>
                                <td style="padding: 22px;">
                                    <h3 style="margin: 0; color: #111928; font-size: 16px; line-height: 24px; font-weight: 700;">Prêt à être examiné</h3>
                                    <p style="margin: 7px 0 0; color: #637381; font-size: 13px; line-height: 21px;">
                                        Ouvrez le dossier dans le Back Office pour vérifier la localisation, poursuivre l'analyse et affecter le signalement.
                                    </p>

                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top: 17px;">
                                        <tr>
                                            <td class="mobile-button" align="center" bgcolor="#3758f9" style="border-radius: 8px; background-color: #3758f9;">
                                                <a href="{{ $detailUrl }}" style="display: inline-block; padding: 13px 24px; color: #ffffff; font-size: 14px; line-height: 20px; font-weight: 700; text-decoration: none; border-radius: 8px;">
                                                    Consulter le signalement
                                                </a>
                                            </td>
                                        </tr>
                                    </table>

                                    <p style="margin: 13px 0 0; color: #667085; font-size: 11px; line-height: 18px;">
                                        Lien sécurisé réservé aux gestionnaires et administrateurs connectés. Les photos éventuellement ajoutées restent accessibles depuis le dossier.
                                    </p>
                                    <p style="margin: 8px 0 0; color: #8899a8; font-size: 10px; line-height: 16px; word-break: break-all;">
                                        <a href="{{ $detailUrl }}" style="color: #667085; text-decoration: underline; word-break: break-all;">{{ $detailUrl }}</a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td class="mobile-padding" style="padding: 25px 34px 28px; background-color: #f9fafb; border-top: 1px solid #eaecf0;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td width="30" valign="middle" style="width: 30px;">
                                    <img src="{{ asset('front/images/favicon.png') }}" width="24" height="24" alt="" style="display: block; width: 24px; max-width: 24px; height: auto;">
                                </td>
                                <td style="padding-left: 8px; color: #344054; font-size: 14px; line-height: 21px; font-weight: 700;">
                                    AquaSecure
                                </td>
                            </tr>
                        </table>
                        <p style="margin: 13px 0 0; color: #667085; font-size: 11px; line-height: 18px;">
                            Cet e-mail a été généré automatiquement par AquaSecure après l'enregistrement d'un signalement citoyen. Merci de ne pas y répondre.
                        </p>
                        <p style="margin: 7px 0 0; color: #98a2b3; font-size: 10px; line-height: 16px;">
                            © {{ date('Y') }} AquaSecure — Surveillance citoyenne de l'eau potable.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
