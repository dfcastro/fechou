@php
    $googleAdsId = config('services.google_ads.id');
    $googleAdsLabel = config('services.google_ads.registration_label');
    $googleAdsRegistrationToken = null;

    // Dashboard can redirect first-time users to onboarding.
    // Read the token only on the first actual onboarding page render.
    if (filled($googleAdsId) && filled($googleAdsLabel)
        && request()->routeIs('onboarding') && auth()->check()) {
        $googleAdsRegistrationToken = session()->pull('google_ads_registration_pending');
    }
@endphp

@if (filled($googleAdsId) && filled($googleAdsLabel))
    @if ($googleAdsRegistrationToken)
        <meta name="negozia-ads-registration" content="{{ $googleAdsRegistrationToken }}">
    @endif
    <script>
        (function () {
            'use strict';

            const adsId = @json($googleAdsId);
            const registrationLabel = @json($googleAdsLabel);
            const registrationToken = @json($googleAdsRegistrationToken);
            const consentKey = 'negozia_google_ads_consent_v1';
            const pendingKey = 'negozia_google_ads_registration_pending_v1';
            let trackingStarted = false;

            function readStorage(type, key) {
                try {
                    return window[type].getItem(key);
                } catch (_) {
                    return null;
                }
            }

            function writeStorage(type, key, value) {
                try {
                    window[type].setItem(key, value);
                } catch (_) {
                    // Tracking is optional; private browsing must not break the site.
                }
            }

            function removeStorage(type, key) {
                try {
                    window[type].removeItem(key);
                } catch (_) {
                    // Tracking is optional.
                }
            }

            if (registrationToken) {
                writeStorage('sessionStorage', pendingKey, registrationToken);
            }

            function consumeRegistrationToken() {
                const tag = document.querySelector('meta[name="negozia-ads-registration"]');
                if (!tag) return;
                const token = tag.getAttribute('content');
                tag.remove();
                if (!token) return;
                writeStorage('sessionStorage', pendingKey, token);
                if (trackingStarted) sendPendingRegistration();
            }

            function sendPendingRegistration() {
                const token = readStorage('sessionStorage', pendingKey);
                if (!token || typeof window.gtag !== 'function') return;
                window.gtag('event', 'conversion', {
                    send_to: adsId + '/' + registrationLabel,
                    value: 0,
                    currency: 'BRL',
                    transaction_id: token
                });
                removeStorage('sessionStorage', pendingKey);
            }

            function startTracking() {
                if (trackingStarted) return;
                trackingStarted = true;

                // Basic consent mode: no Google request before explicit opt-in.
                window.dataLayer = window.dataLayer || [];
                window.gtag = window.gtag || function () {
                    window.dataLayer.push(arguments);
                };

                window.gtag('consent', 'default', {
                    ad_storage: 'denied',
                    ad_user_data: 'denied',
                    ad_personalization: 'denied',
                    analytics_storage: 'denied'
                });
                window.gtag('consent', 'update', {
                    ad_storage: 'granted',
                    ad_user_data: 'denied',
                    ad_personalization: 'denied',
                    analytics_storage: 'denied'
                });
                window.gtag('js', new Date());
                window.gtag('config', adsId);

                const script = document.createElement('script');
                script.async = true;
                script.src = 'https://www.googletagmanager.com/gtag/js?id='
                    + encodeURIComponent(adsId);
                document.head.appendChild(script);

                sendPendingRegistration();
            }

            function showConsentBanner() {
                if (document.getElementById('negozia-ads-consent')) return;

                const banner = document.createElement('aside');
                banner.id = 'negozia-ads-consent';
                banner.setAttribute('role', 'dialog');
                banner.setAttribute('aria-label', 'Cookies de publicidade');
                banner.style.cssText = 'position:fixed;bottom:16px;left:16px;right:16px;'
                    + 'max-width:480px;z-index:10000;padding:16px;border-radius:12px;'
                    + 'background:#fff;color:#18181b;border:1px solid #d4d4d8;'
                    + 'box-shadow:0 10px 35px rgba(0,0,0,.16);font:14px/1.5 sans-serif';

                const message = document.createElement('p');
                message.textContent = 'Podemos usar cookies de publicidade do Google '
                    + 'para medir visitas e cadastros provenientes dos anúncios. '
                    + 'Você pode aceitar ou recusar. O NegoZia funciona normalmente sem esses cookies.';
                message.style.margin = '0 0 12px';

                const choices = document.createElement('div');
                choices.style.cssText = 'display:flex;gap:8px;flex-wrap:wrap';

                function addChoice(label, decision, background, color) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = label;
                    button.style.cssText = 'padding:8px 14px;border-radius:8px;'
                        + 'border:1px solid #a1a1aa;cursor:pointer;font-weight:600';
                    button.style.background = background;
                    button.style.color = color;
                    button.addEventListener('click', function () {
                        writeStorage('localStorage', consentKey, decision);
                        banner.remove();
                        showPreferenceButton();
                        if (decision === 'granted') {
                            if (trackingStarted) {
                                window.gtag('consent', 'update', { ad_storage: 'granted' });
                            } else {
                                startTracking();
                            }
                        } else if (trackingStarted) {
                            window.gtag('consent', 'update', { ad_storage: 'denied' });
                        }
                    });
                    choices.appendChild(button);
                }

                addChoice('Recusar', 'denied', '#fff', '#18181b');
                addChoice('Aceitar', 'granted', '#059669', '#fff');
                banner.append(message, choices);
                document.body.appendChild(banner);
            }

            function showPreferenceButton() {
                if (document.getElementById('negozia-ads-preferences')) return;
                const button = document.createElement('button');
                button.id = 'negozia-ads-preferences';
                button.type = 'button';
                button.textContent = 'Cookies';
                button.setAttribute('aria-label', 'Alterar consentimento de publicidade');
                button.style.cssText = 'position:fixed;bottom:12px;right:12px;'
                    + 'z-index:9999;border:1px solid #a1a1aa;border-radius:8px;'
                    + 'background:#fff;color:#27272a;font:12px sans-serif;'
                    + 'padding:6px 10px;cursor:pointer';
                button.addEventListener('click', function () {
                    button.remove();
                    showConsentBanner();
                });
                document.body.appendChild(button);
            }

            if (readStorage('localStorage', consentKey) === 'granted') {
                startTracking();
            }

            document.addEventListener('livewire:navigated', consumeRegistrationToken);
            document.addEventListener('DOMContentLoaded', function () {
                consumeRegistrationToken();
                const choice = readStorage('localStorage', consentKey);
                if (choice === 'granted' || choice === 'denied') {
                    showPreferenceButton();
                } else {
                    showConsentBanner();
                }
            });
        })();
    </script>
@endif
