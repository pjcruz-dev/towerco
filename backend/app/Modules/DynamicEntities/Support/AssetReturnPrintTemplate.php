<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

/**
 * Asset return checklist used when an employee turns equipment back in.
 */
final class AssetReturnPrintTemplate
{
    public const TEMPLATE_ID = 'it-asset-return-checklist';

    public const SOURCE_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        return [
            'id' => self::TEMPLATE_ID,
            'name' => 'Return Checklist',
            'list_label' => 'Return Checklist',
            'document_title' => 'ASSET RETURN CHECKLIST',
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
  <h1>ASSET RETURN CHECKLIST</h1>
  <p class="acct-intro">{{record.number}} · {{record.assignee_name}}</p>
  <table class="acct-items">
    <thead>
      <tr>
        <th>TAG</th>
        <th>ITEM</th>
        <th>SERIAL NUMBER</th>
        <th>RESULT</th>
        <th>CONDITION</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>{{item.asset_tag}}</td>
        <td>{{item.item}}</td>
        <td>{{item.serial_number}}</td>
        <td>{{item.result}}</td>
        <td>{{item.condition}}</td>
      </tr>
    </tbody>
  </table>
  <p>The employee returns the items listed above. IT confirms each tag, the return result, and the condition at turnover.</p>
  <div class="acct-signs">
    <div>
      <div class="acct-sign-line"></div>
      <p class="acct-sign-label">Employee signature / Date</p>
      <p class="acct-sign-name">{{record.assignee_name}}</p>
    </div>
    <div>
      <div class="acct-sign-line"></div>
      <p class="acct-sign-label">IT signature / Date</p>
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
.acct-signs { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 36px; }
.acct-sign-line { border-bottom: 1px solid #111; }
.acct-note p.acct-sign-label { margin: 4px 0 0; text-align: left; font-weight: 700; }
.acct-note p.acct-sign-name { margin: 2px 0 0; text-align: left; }
CSS;
    }
}
