@php
    $analyticsEnabled = \App\Models\Configuration::get('analytics_enabled', false);
    $measurementId = \App\Models\Configuration::get('analytics_measurement_id');
@endphp

@if($analyticsEnabled && $measurementId)
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $measurementId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', '{{ $measurementId }}');
    </script>
@endif
