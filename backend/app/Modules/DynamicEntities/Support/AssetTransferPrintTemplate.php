<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

/**
 * Transfer-of-accountability note for the IT asset entity.
 */
final class AssetTransferPrintTemplate
{
    public const TEMPLATE_ID = 'it-asset-transfer-accountability';

    public const SOURCE_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        return [
            'id' => self::TEMPLATE_ID,
            'name' => 'Transfer Accountability',
            'list_label' => 'Transfer Accountability',
            'document_title' => 'TRANSFER OF ACCOUNTABILITY',
            'layout' => 'grouped',
            'status_stamp' => false,
            'signatures' => true,
            'signature_style' => 'prepared',
            'company_name' => 'Alliance Towers Corporation',
            'company_address' => null,
            'subtitle_fields' => [],
            'subtitle_prefix' => null,
            'title_align' => 'center',
            'highlight_fields' => [],
            'currency_fields' => [],
            'terms_paragraphs' => [],
            'orientation' => 'portrait',
            'source_version' => self::SOURCE_VERSION,
            'template_html' => self::html(),
            'template_css' => self::css(),
        ];
    }

    private static function html(): string
    {
        return <<<'HTML'
<div class="acct-note">
  <div class="acct-logo">
    {{#if system.company_logo}}
    <img src="{{system.company_logo}}" alt="" class="acct-logo-img" />
    {{else}}
    <div class="acct-mark" aria-hidden="true"></div>
    <div class="acct-wordmark">ALLIANCE<br/>TOWERS</div>
    {{/if}}
  </div>
  <h1>TRANSFER OF ACCOUNTABILITY</h1>
  <p class="acct-intro">This is to confirm that the company property described below is transferred to the receiving employee, who accepts accountability for it from this date.</p>
  <table class="acct-items">
    <thead>
      <tr>
        <th>TAG</th>
        <th>ITEM</th>
        <th>BRAND</th>
        <th>SERIAL NUMBER</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>{{item.asset_tag}}</td>
        <td>{{item.item}}</td>
        <td>{{item.brand}}</td>
        <td>{{item.serial_number}}</td>
      </tr>
    </tbody>
  </table>
  <p>The receiving employee, <strong>{{record.assignee_name}}</strong>, accepts the item in its present condition and bears the accountability for its maintenance and safeguarding. Loss or damage caused by negligence remains the receiving employee's responsibility.</p>
  <p>The releasing party confirms the item is no longer in their custody. Alliance Towers Corporation retains ownership and may recall or reassign the item.</p>
  <p class="acct-check"><span class="acct-box"></span> Received with defect(s) — describe below</p>
  <p class="acct-check"><span class="acct-box"></span> <u>Received with no defects</u></p>
  <p class="acct-blank">Defects / notes: _______________________________________________</p>
  <div class="acct-signs">
    <div>
      <div class="acct-sign-line"></div>
      <p class="acct-sign-label">Released by / Date</p>
    </div>
    <div>
      <div class="acct-sign-line"></div>
      <p class="acct-sign-label">Received by / Date</p>
      <p class="acct-sign-name">{{record.assignee_name}}</p>
    </div>
  </div>
</div>
HTML;
    }

    private static function css(): string
    {
        return <<<'CSS'
@page { size: Letter; margin: 0.7in; }
.acct-note { max-width: 7.4in; margin: 0 auto; color: #111; font-family: "Times New Roman", Times, serif; font-size: 11pt; line-height: 1.35; }
.acct-logo { display: flex; align-items: center; gap: 8px; min-height: 52px; margin-bottom: 8px; }
.acct-logo-img { max-height: 64px; max-width: 180px; }
.acct-mark { width: 0; height: 0; border-left: 18px solid transparent; border-right: 18px solid transparent; border-bottom: 32px solid #1d4e89; }
.acct-wordmark { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; font-weight: 700; letter-spacing: 0.04em; color: #1d4e89; line-height: 1.15; }
.acct-note h1 { margin: 8px 0 14px; text-align: center; font-size: 13pt; font-weight: 700; letter-spacing: 0.04em; }
.acct-note p.acct-intro { text-align: center; margin: 0 0 12px; }
.acct-items { width: 100%; border-collapse: collapse; margin: 0 0 14px; }
.acct-items th, .acct-items td { border: 1px solid #111; padding: 4px 8px; text-align: center; }
.acct-items th { font-size: 10pt; font-weight: 700; }
.acct-note p { margin: 0 0 10px; text-align: justify; }
.acct-note p.acct-check, .acct-note p.acct-blank { text-align: left; }
.acct-box { display: inline-block; width: 11px; height: 11px; border: 1px solid #111; margin-right: 6px; vertical-align: -1px; }
.acct-signs { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 28px; }
.acct-sign-line { border-bottom: 1px solid #111; }
.acct-note p.acct-sign-label { margin: 4px 0 0; text-align: left; font-weight: 700; }
.acct-note p.acct-sign-name { margin: 2px 0 0; text-align: left; }
CSS;
    }
}
