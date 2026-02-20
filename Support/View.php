<?php
declare(strict_types=1);

namespace App\Support;

final class View
{
    /**
     * Renderiza um template PHP da pasta app/Views.
     * Ex.: View::render('form', ['title' => 'Formulário', 'old' => []])
     */
    public static function render(string $template, array $data = []): string
    {
        // Caminho base das views
        $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views';

        // Permite subpastas: "audits/form" -> app/Views/audits/form.php
        $template = trim($template, "/\\");
        $path = $baseDir . DIRECTORY_SEPARATOR . $template . '.php';

        if (!is_file($path)) {
            // Mensagem clara para debugar rapidamente
            $safe = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
            return "<div style=\"color:#b91c1c;background:#fee2e2;padding:10px;border:1px solid #ef4444;border-radius:6px\">
                        Template não encontrado: <code>{$safe}</code>
                    </div>";
        }

        // Extrai dados em variáveis locais
        if (!empty($data)) {
            // Evita sobrescrever variáveis internas
            extract($data, EXTR_SKIP);
        }

        // Captura a saída do template
        ob_start();
        require $path;
        return ob_get_clean() ?: '';
    }
}