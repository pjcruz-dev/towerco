<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

/**
 * Accountability note attached to the IT asset entity.
 * Wording follows the Alliance Towers accountability form.
 */
final class ItAssetPrintTemplate
{
    public const TEMPLATE_ID = 'it-asset-accountability-note';

    public const SOURCE_VERSION = 2;

    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        return [
            'id' => self::TEMPLATE_ID,
            'name' => 'Accountability Note',
            'list_label' => 'Accountability Note',
            'document_title' => 'ACCOUNTABILITY NOTE',
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
  <h1>ACCOUNTABILITY NOTE</h1>
  <p class="acct-intro">This is to confirm that Alliance Towers Corporation hereby assigned to the item/s described below.</p>
  <table class="acct-items">
    <thead>
      <tr>
        <th>QUANTITY</th>
        <th>ITEM</th>
        <th>BRAND</th>
        <th>SERIAL NUMBER</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>{{item.quantity}}</td>
        <td>{{item.item}}</td>
        <td>{{item.brand}}</td>
        <td>{{item.serial_number}}</td>
      </tr>
    </tbody>
  </table>
  <p>This Note bears the Assignee the accountability in the maintenance and safeguarding of the said company property. Thus, in case of loss and/or any defects in the item incurred due to Assignee's negligence, it will be Assignee's sole responsibility.</p>
  <p>Alliance Towers Corporation deemed the ownership of the said item. Thus, giving rights to the company in re-calling or re-assigning the said item to anyone based on the company's prerogative.</p>
  <p>If the Assignee fails to relinquish the said item to the company, Assignee agrees to pay Alliance Towers Corporation an amount which is in accordance with the following criteria:</p>
  <p class="acct-criteria">Within one (1) year &nbsp;&nbsp;: &nbsp;&nbsp;Full Amount (current)<br/>More than one (1) year &nbsp;&nbsp;: &nbsp;&nbsp;Half of the Full Amount</p>
  <p>If the employee resigns, the item indicated above shall be subjected to checking for clearance purposes. If defects have been found, the amount for repair shall be deducted to the last pay.</p>
  <p>The undersigned parties agree to the terms and conditions presented in this letter. Alliance Towers Corporation acknowledges receipt of this document as a part of the company's official Note.</p>
  <p>The parties enter this Note and accept this Note as official as of the date stated below.</p>
  <p class="acct-inspect">Please thoroughly inspect the issued laptop and indicate below any existing defects, whether PHYSICAL (such as cracks, dents, scratches, screen anomalies, or other visible damage) or related to SOFTWARE or SYSTEM functionality. Kindly provide a detailed description of each identified issue. Supporting photographs may be submitted, if necessary.</p>
  <p>Should no defects be reported, it shall be understood and acknowledged that the laptop is in good working condition as of the date of signing and submission of this Accountability Form.</p>
  <p class="acct-check"><span class="acct-box"></span> With defect(s) – see photo, description</p>
  <p class="acct-check"><span class="acct-box"></span> <u>NO defects</u></p>
  <div class="acct-sign-line"></div>
  <p class="acct-sign-label">Employee Name/Signature/Date</p>
  <p class="acct-sign-name">{{record.assignee_name}}</p>
</div>
HTML;
    }

    private static function css(): string
    {
        return <<<'CSS'
@page { size: Letter; margin: 0.7in; }
.acct-note {
  max-width: 7.4in;
  margin: 0 auto;
  color: #111;
  font-family: "Times New Roman", Times, serif;
  font-size: 11pt;
  line-height: 1.35;
}
.acct-logo { display: flex; align-items: center; gap: 8px; min-height: 52px; margin-bottom: 8px; }
.acct-logo-img { max-height: 64px; max-width: 180px; }
.acct-mark {
  width: 0;
  height: 0;
  border-left: 18px solid transparent;
  border-right: 18px solid transparent;
  border-bottom: 32px solid #1d4e89;
}
.acct-wordmark { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; font-weight: 700; letter-spacing: 0.04em; color: #1d4e89; line-height: 1.15; }
.acct-note h1 { margin: 8px 0 14px; text-align: center; font-size: 13pt; font-weight: 700; letter-spacing: 0.04em; }
.acct-note p.acct-intro { text-align: center; margin: 0 0 12px; }
.acct-items { width: 100%; border-collapse: collapse; margin: 0 0 14px; }
.acct-items th, .acct-items td { border: 1px solid #111; padding: 4px 8px; text-align: center; }
.acct-items th { font-size: 10pt; font-weight: 700; }
.acct-note p { margin: 0 0 10px; text-align: justify; }
.acct-note p.acct-criteria { text-align: center; }
.acct-inspect { margin-top: 8px; }
.acct-note p.acct-check { text-align: left; margin: 2px 0; }
.acct-box { display: inline-block; width: 11px; height: 11px; border: 1px solid #111; margin-right: 6px; vertical-align: -1px; }
.acct-sign-line { margin-top: 28px; border-bottom: 1px solid #111; }
.acct-note p.acct-sign-label { margin: 4px 0 0; text-align: left; font-weight: 700; }
.acct-note p.acct-sign-name { margin: 2px 0 0; text-align: left; }
CSS;
    }
}
