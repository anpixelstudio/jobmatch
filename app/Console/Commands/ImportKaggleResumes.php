<?php

namespace App\Console\Commands;

use App\Models\Candidate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportKaggleResumes extends Command
{
    protected $signature = 'jobmatch:import-kaggle
        {--path= : Path ke folder dataset-cv}
        {--limit=0 : Batasi jumlah baris (0 = semua)}';

    protected $description = 'Import dataset CV Kaggle (Resume.csv + PDF) ke tabel candidates';

    public function handle(): int
    {
        $default = dirname(base_path()).'/04. Data/dataset-cv';
        $base = $this->option('path') ?: $default;
        $base = realpath($base) ?: $base;

        $csv = rtrim($base, '/').'/Resume/Resume.csv';
        if (! is_file($csv)) {
            $this->error("CSV tidak ditemukan: {$csv}");

            return self::FAILURE;
        }

        $pdfRoot = rtrim($base, '/').'/data/data';
        $limit = (int) $this->option('limit');

        $this->info("Membaca {$csv}");

        $handle = fopen($csv, 'r');
        if ($handle === false) {
            $this->error('Gagal membuka CSV');

            return self::FAILURE;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            $this->error('CSV kosong');
            fclose($handle);

            return self::FAILURE;
        }

        $header = array_map(fn ($h) => trim((string) $h), $header);
        $inserted = 0;
        $updated = 0;
        $rowNum = 0;

        DB::connection()->disableQueryLog();

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($limit > 0 && $rowNum > $limit) {
                break;
            }

            if (count($row) < count($header)) {
                continue;
            }

            $data = array_combine($header, $row);
            if ($data === false) {
                continue;
            }

            $externalId = (string) ($data['ID'] ?? '');
            if ($externalId === '') {
                continue;
            }

            $category = (string) ($data['Category'] ?? '');
            $raw = (string) ($data['Resume_str'] ?? '');
            $pdfPath = null;
            $candidatePdf = $pdfRoot.'/'.$category.'/'.$externalId.'.pdf';
            if (is_file($candidatePdf)) {
                $pdfPath = $candidatePdf;
            }

            $name = $this->guessName($raw) ?: ('CV '.$category.' #'.$externalId);

            $payload = [
                'source' => 'kaggle',
                'category' => $category !== '' ? $category : null,
                'name' => $name,
                'raw_text' => $raw,
                'pdf_path' => $pdfPath,
                'original_filename' => $externalId.'.pdf',
                'status' => 'ready',
                'updated_at' => now(),
            ];

            $existing = Candidate::query()->where('external_id', $externalId)->first();
            if ($existing) {
                $existing->fill($payload)->save();
                $updated++;
            } else {
                Candidate::query()->create([
                    ...$payload,
                    'external_id' => $externalId,
                    'created_at' => now(),
                ]);
                $inserted++;
            }

            if (($inserted + $updated) % 250 === 0) {
                $this->line('… '.($inserted + $updated).' diproses');
            }
        }

        fclose($handle);

        $this->info("Selesai. Insert: {$inserted}, Update: {$updated}");

        return self::SUCCESS;
    }

    protected function guessName(string $raw): ?string
    {
        $lines = preg_split('/\R/', trim($raw)) ?: [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');
            if ($line === '' || strlen($line) < 3 || strlen($line) > 60) {
                continue;
            }
            if (preg_match('/(summary|experience|education|skills|objective|profile|contact)/i', $line)) {
                continue;
            }
            if (preg_match('/^[A-Za-z][A-Za-z .\'-]{2,50}$/', $line)) {
                return $line;
            }
        }

        return null;
    }
}
