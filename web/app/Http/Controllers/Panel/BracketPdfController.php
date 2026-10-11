<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Tournament\PaperBracket;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** Cuadro en PDF (A4 horizontal) para rellenar a mano. */
class BracketPdfController extends Controller
{
    public function __invoke(Event $event, Category $category, PaperBracket $paper): Response
    {
        abort_if($category->bouts()->doesntExist(), 404);

        $html = view('pdf.bracket', ['event' => $event, 'category' => $category, 'pages' => $paper->pages($category)])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $name = Str::slug($event->name.' '.$category->name).'-cuadro.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
