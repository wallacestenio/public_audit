<?php

namespace App\Services;

use App\Repositories\AuditEntryRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet as ExcelSpreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as ExcelXlsx;


class ExportAuditoriaMensal
{
    private string $templatePath;
    private string $tmpExportPath;
    private AuditEntryRepository $auditEntryRepository;

    public function __construct(AuditEntryRepository $auditEntryRepository)
{
    $projectRoot = dirname(__DIR__, 2);

    $this->templatePath  = $projectRoot . '/storage/export-templates';
    $this->tmpExportPath = $projectRoot . '/tmp/exports';

    $this->auditEntryRepository = $auditEntryRepository;
}

    public function exportar(string $mes): string
    {
        $template = $this->obterTemplateAtivo();
        $arquivoDestino = $this->criarCopiaDeTrabalho($template, $mes);

        $spreadsheet = $this->carregarPlanilha($arquivoDestino);

        // ✅ ETAPA 2 – preenchimento do template
        $this->preencherPlanilha($spreadsheet, $mes);

        $this->salvarPlanilha($spreadsheet, $arquivoDestino);

        return $arquivoDestino;
    }

    
private function preencherPlanilha(ExcelSpreadsheet $spreadsheet, string $mes): void
{
    $sheet = $spreadsheet->getActiveSheet();

    // ✅ REMOVE COMPLETAMENTE as linhas 2 e 3 do template
    // (onde hoje aparecem "2026-04" e "Auditoria Mensal de Chamados")
    $sheet->removeRow(2, 2);

    // ✅ Buscar dados reais do banco
    $rows = $this->auditEntryRepository->exportRows([
        'audit_month' => $mes,
    ]);

    // ✅ Dados começam exatamente na linha A2
    $linha = 2;

    foreach ($rows as $row) {
        $sheet->setCellValue('A' . $linha, $row['ticket_number']);
        $sheet->setCellValue('B' . $linha, $row['ticket_type']);
        $sheet->setCellValue('C' . $linha, $row['kyndryl_auditor']);
        $sheet->setCellValue('D' . $linha, $row['petrobras_inspector']);
        $sheet->setCellValue('E' . $linha, $row['audited_supplier']);
        $sheet->setCellValue('F' . $linha, $row['location']);
        $sheet->setCellValue('G' . $linha, $row['audit_month']);
        $sheet->setCellValue('H' . $linha, $row['priority_label']);
        $sheet->setCellValue('I' . $linha, $row['category']);
        $sheet->setCellValue('J' . $linha, $row['resolver_group']);
        $sheet->setCellValue('K' . $linha, $row['sla_met_label']);
        $sheet->setCellValue('L' . $linha, $row['is_compliant_label']);
        $sheet->setCellValue('M' . $linha, $row['noncompliance_reasons']);

        $linha++;
    }
}

    private function obterTemplateAtivo(): string
    {
        $template = $this->templatePath . '/auditoria-kyndryl-v1.xlsx';

        if (!file_exists($template)) {
            throw new \RuntimeException('Template de exportação não encontrado.');
        }

        return $template;
    }

    private function criarCopiaDeTrabalho(string $template, string $mes): string
    {
        if (!is_dir($this->tmpExportPath)) {
            mkdir($this->tmpExportPath, 0775, true);
        }

        $destino = $this->tmpExportPath . '/Auditoria_Chamados_' . $mes . '.xlsx';

        if (!copy($template, $destino)) {
            throw new \RuntimeException('Falha ao criar cópia do template.');
        }

        return $destino;
    }

    private function carregarPlanilha(string $arquivo): ExcelSpreadsheet
{
    return IOFactory::load($arquivo);
}


    private function salvarPlanilha(ExcelSpreadsheet $spreadsheet, string $caminho): void
{
    $writer = new ExcelXlsx($spreadsheet);
    $writer->save($caminho);
}
}