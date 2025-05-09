<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit; ?>
<p class="fastpixel-tab-description">Choose from three presets to optimize your website's speed. The "Fast" option may cause glitches or incompatibilities on antypical website setups, so test your site thoroughly before using it.</p>
<div class="fastpixel-presets-container">
    <div class="fastpixel-presets-box safe">
        <div class="box-title">Safe<span>(Active)</span></div>
        <ul class="options-list">
            <li>Optimized HTML & CSS</li>
            <li>CDN delivery</li>
            <li>DNS prefetching and preloading</li>
            <li>No JavaScript optimization is performed, the scripts run exactly as on the original page</li>
            <li>Lossless image SmartCompression with lazyloading: the resulting image is pixel-identical with the original image</li>
            <li>Font Optimization with safe fallback to original fonts</li>
            <li>Speculation Rules Disabled</li>
        </ul>
        <button class="btn apply-preset" data-preset="safe"><span class="apply">Apply Preset</span><span class="active">Active</span></button>
    </div>
    <div class="fastpixel-presets-box basic">
        <div class="box-title">Balanced<span>(Active)</span></div>
        <ul class="options-list">
            <li>Optimized HTML & CSS</li>
            <li>CDN delivery</li>
            <li>DNS prefetching and preloading</li>
            <li>All scripts are optimized and run as on the original page</li>
            <li>Glossy image SmartCompression with lazyloading: creates images that are almost pixel-perfect identical with the originals</li>
            <li>Font Optimization with safe fallback to original fonts</li>
            <li>Moderate Speculation Rules</li>
        </ul>
        <button class="btn apply-preset" data-preset="basic"><span class="apply">Apply Preset</span><span class="active">Active</span></button>
    </div>
    <div class="fastpixel-presets-box fast">
        <div class="box-title">Fast<span>(Active)</span></div>
        <ul class="options-list">
            <li>Optimized HTML & CSS</li>
            <li>CDN delivery</li>
            <li>DNS prefetching and preloading</li>
            <li>All scripts are optimized and delayed, except necessary scripts like GDPR</li>
            <li>Lossy image SmartCompression with lazyloading and adaptive resizing: offers the best compression rate</li>
            <li>Images cropped to reduce size and fit better</li>
            <li>Strong Font Optimization</li>
            <li>Eager Speculation Rules</li>
        </ul>
        <button class="btn apply-preset" data-preset="fast"><span class="apply">Apply Preset</span><span class="active">Active</span></button>
    </div>
</div>
