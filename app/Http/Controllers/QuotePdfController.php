<?php

namespace App\Http\Controllers;

use App\Models\Quote;
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
        $logoDataUri = $this->logoDataUri(
            $quote->business->logo_path
        );

        return Pdf::loadView(
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
        if (! $path) {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
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
