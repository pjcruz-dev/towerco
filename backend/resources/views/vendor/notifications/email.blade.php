@inject('urlResolver', 'App\Modules\Tenancy\Support\TenantAppUrlResolver')
<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Regards,')<br>
{{ config('app.name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
@php
    $ticketingEnabled = in_array(
        'ticketing',
        app(\App\Modules\Tenancy\Support\TenantEnabledModulesResolver::class)->resolveForCurrentTenant(),
        true
    );
@endphp
<x-slot:subcopy>
@if ($ticketingEnabled)
If you're unable to access this submission, please [create an IT support ticket]({{ $urlResolver->urlForCurrentTenant('/ticketing/tickets/new') }}) and include the link below to help us investigate:
@else
If you're unable to access this submission, contact your IT support team and include the link below to help them investigate:
@endif

<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
