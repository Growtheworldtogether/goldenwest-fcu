@extends('layouts.inner')
@section('title', 'Accessibility | Golden West Federal Credit Union')
@section('crumb', 'Accessibility')
@section('heading', 'Accessibility')
@section('intro', 'We want everyone to be able to bank with us.')

@section('body')
<section class="sec"><div class="wrap"><div class="prose">
<h2>Our commitment</h2><p>Golden West is committed to making our website and digital banking usable by everyone, including people who use screen readers, keyboard navigation or other assistive technology. We aim to meet the Web Content Accessibility Guidelines (WCAG) 2.1 at level AA.</p>
<h2>What we do</h2><ul><li>Every page can be used with a keyboard alone.</li><li>Images and icons have text alternatives.</li><li>Color is never the only way we show information.</li><li>Text can be enlarged without losing content.</li></ul>
<h2>Need help?</h2><p>If you have trouble using any part of our site, or need information in another format, tell us at <a href="tel:8005550147">800-555-0147</a> or through our <a href="{{ url('/contact') }}">contact page</a>. We will respond within one business day.</p>
</div></div></section>
@endsection
