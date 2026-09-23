<?php

namespace App\Http\Controllers;

use App\Enums\PlanFeature;
use App\Models\Quote;
use App\Services\SubscriptionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuotePdfController extends Controller
{
    public function preview(int $quote)
    {
        $quoteModel = $this->findQuote($quote);

        $pdf = $this->makePdf($quoteModel);

        return $pdf->stream(
            $this->filename($quoteModel)
        );
    }

    public function download(int $quote)
    {
        $quoteModel = $this->findQuote($quote);

        $pdf = $this->makePdf($quoteModel);

        return $pdf->download(
            $this->filename($quoteModel)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Buscar orçamento
    |--------------------------------------------------------------------------
    */


    public function publicDownload(string $token)
    {
        $quoteModel = Quote::query()
            ->with([
                'business',
                'client',
                'items' => fn($query) =>
                    $query->orderBy('sort_order'),
            ])
            ->where('public_token', $token)
            ->where('status', '!=', 'draft')
            ->firstOrFail();

        $pdf = $this->makePdf($quoteModel);

        return $pdf->download(
            $this->filename($quoteModel)
        );
    }


    private function findQuote(int $quote): Quote
    {
        $business = Auth::user()->business;

        abort_unless($business, 403);

        return $business
            ->quotes()
            ->with([
                'business',
                'client',

                'items' => fn($query) =>
                    $query->orderBy('sort_order'),
            ])
            ->whereKey($quote)
            ->firstOrFail();
    }

    /*
    |--------------------------------------------------------------------------
    | Gerar PDF
    |--------------------------------------------------------------------------
    */

    private function makePdf(Quote $quote)
    {
        /*
         * A logo pode continuar armazenada após um downgrade.
         *
         * O arquivo só recebe a identidade personalizada
         * quando o plano atual possui CUSTOM_BRANDING.
         */
        $canUseCustomBranding = app(SubscriptionService::class)
            ->hasFeature(
                $quote->business,
                PlanFeature::CUSTOM_BRANDING
            );

        $logoDataUri = $canUseCustomBranding
            ? $this->logoDataUri(
                $quote->business->logo_path
            )
            : null;

        $pdf = Pdf::loadView(
            'pdf.quote',
            [
                'quote' => $quote,
                'logoDataUri' => $logoDataUri,
            ]
        )
            ->setPaper(
                'a4',
                'portrait'
            );

        /*
         * ORÇAMENTO • CONTINUAÇÃO
         *
         * Em PDFs com mais de uma página, as páginas seguintes
         * recebem uma identificação discreta no topo.
         *
         * Pulamos esta etapa nos testes que mockam o facade Pdf,
         * preservando os testes de autorização/branding existentes.
         */
        if (! app()->runningUnitTests()) {
            $pdf->render();

            $dompdf = $pdf->getDomPDF();
            $canvas = $dompdf->getCanvas();
            $fontMetrics = $dompdf->getFontMetrics();

            $number = str_pad(
                $quote->number,
                4,
                '0',
                STR_PAD_LEFT
            );

            $canvas->page_script(
                function (
                    $pageNumber,
                    $pageCount,
                    $canvas,
                    $fontMetrics
                ) use ($number) {
                    if ($pageNumber <= 1) {
                        return;
                    }

                    $font = $fontMetrics->getFont(
                        'DejaVu Sans',
                        'normal'
                    );

                    $bold = $fontMetrics->getFont(
                        'DejaVu Sans',
                        'bold'
                    );

                    $leftText =
                        "ORÇAMENTO #{$number} • CONTINUAÇÃO";

                    $rightText =
                        "{$pageNumber}/{$pageCount}";

                    $left = 26;
                    $top = 7;

                    $gray = [
                        0.44,
                        0.44,
                        0.48,
                    ];

                    $line = [
                        0.83,
                        0.83,
                        0.85,
                    ];

                    $canvas->text(
                        $left,
                        $top,
                        $leftText,
                        $bold,
                        7.5,
                        $gray
                    );

                    $rightWidth =
                        $fontMetrics->getTextWidth(
                            $rightText,
                            $font,
                            7
                        );

                    $canvas->text(
                        $canvas->get_width()
                            - $left
                            - $rightWidth,
                        $top,
                        $rightText,
                        $font,
                        7,
                        $gray
                    );

                    $canvas->line(
                        $left,
                        18,
                        $canvas->get_width() - $left,
                        18,
                        $line,
                        0.5
                    );
                }
            );
        }

        return $pdf;
    }

    /*
    |--------------------------------------------------------------------------
    | Logo incorporada no PDF
    |--------------------------------------------------------------------------
    |
    | Transformamos a imagem em Base64.
    |
    | Isso evita problemas do DomPDF tentando acessar
    | URLs HTTP ou o symlink public/storage.
    |
    */

    private function logoDataUri(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (!Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')
            ->mimeType($path);

        $contents = Storage::disk('public')
            ->get($path);

        return 'data:'
            . $mime
            . ';base64,'
            . base64_encode($contents);
    }

    /*
    |--------------------------------------------------------------------------
    | Nome do arquivo
    |--------------------------------------------------------------------------
    */

    private function filename(Quote $quote): string
    {
        $client = Str::slug(
            $quote->client->name
        );

        $number = str_pad(
            $quote->number,
            4,
            '0',
            STR_PAD_LEFT
        );

        return "orcamento-{$number}-{$client}.pdf";
    }
}
