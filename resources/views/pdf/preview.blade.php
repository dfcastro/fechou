<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Proposta #{{ str_pad($quote->number, 4, '0', STR_PAD_LEFT) }} | Negozia
    </title>

    <style>
        :root {
            color-scheme: light dark;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            width: 100%;
            min-height: 100%;
        }

        body {
            min-height: 100dvh;
            overflow: hidden;
            background: #f4f4f5;
            color: #18181b;
        }

        .pdf-frame {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100dvh;
            border: 0;
            opacity: 0;
            background: #ffffff;
            transition: opacity 160ms ease;
        }

        .loading {
            position: fixed;
            inset: 0;
            z-index: 10;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(
                    circle at 50% 35%,
                    rgba(16, 185, 129, 0.08),
                    transparent 34%
                ),
                #fafafa;
            transition:
                opacity 160ms ease,
                visibility 160ms ease;
        }

        .loading-card {
            width: min(100%, 380px);
            text-align: center;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #18181b;
        }

        .spinner {
            width: 42px;
            height: 42px;
            margin: 0 auto 20px;
            border: 3px solid #d4d4d8;
            border-top-color: #10b981;
            border-radius: 999px;
            animation: spin 700ms linear infinite;
        }

        h1 {
            margin: 0;
            font-size: 20px;
            line-height: 1.25;
            letter-spacing: -0.025em;
        }

        p {
            margin: 9px 0 0;
            color: #71717a;
            font-size: 14px;
            line-height: 1.55;
        }

        body.pdf-ready .loading {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        body.pdf-ready .pdf-frame {
            opacity: 1;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (prefers-color-scheme: dark) {
            body {
                background: #09090b;
                color: #fafafa;
            }

            .loading {
                background:
                    radial-gradient(
                        circle at 50% 35%,
                        rgba(16, 185, 129, 0.12),
                        transparent 34%
                    ),
                    #09090b;
            }

            .brand {
                color: #fafafa;
            }

            .spinner {
                border-color: #3f3f46;
                border-top-color: #34d399;
            }

            p {
                color: #a1a1aa;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .spinner {
                animation-duration: 1400ms;
            }

            .loading,
            .pdf-frame {
                transition: none;
            }
        }
    </style>
</head>

<body>
    <div
        class="loading"
        role="status"
        aria-live="polite"
    >
        <div class="loading-card">
            <div class="brand">
                Negozia
            </div>

            <div
                class="spinner"
                aria-hidden="true"
            ></div>

            <h1>
                Gerando sua proposta...
            </h1>

            <p>
                Estamos preparando o documento para revisão.
            </p>
        </div>
    </div>

    <iframe
        id="pdf-frame"
        class="pdf-frame"
        src="{{ $pdfUrl }}"
        title="Proposta em PDF"
    ></iframe>

    <script>
        document
            .getElementById('pdf-frame')
            .addEventListener('load', () => {
                document.body.classList.add('pdf-ready');
            });
    </script>
</body>
</html>
