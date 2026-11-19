<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Sao lưu CSDL ra storage/app/sao-luu/dat_san-YYYY-mm-dd_HHiiss.sql.gz, xoá bản cũ hơn SAO_LUU_GIU_NGAY ngày.
 * Lịch tự chạy 02:30 mỗi đêm (routes/console.php). Chạy tay: php artisan sao-luu:csdl
 * Phục hồi: gunzip -c file.sql.gz | mariadb -u USER -p dat_san
 */
class SaoLuuCsdl extends Command
{
    protected $signature = 'sao-luu:csdl {--chi-don : Chỉ xoá bản sao lưu cũ, không tạo bản mới}';

    protected $description = 'Sao lưu CSDL (nén .gz) và dọn bản cũ';

    public function handle(): int
    {
        $thuMuc = storage_path('app/sao-luu');
        File::ensureDirectoryExists($thuMuc);

        if (! $this->option('chi-don') && ($loi = $this->taoBan($thuMuc))) {
            $this->error($loi);

            return self::FAILURE;
        }

        $han = now()->subDays(max(1, config('bao_mat.sao_luu_giu_ngay', 14)))->getTimestamp();
        $xoa = 0;
        foreach (File::glob($thuMuc.'/dat_san-*.gz') as $f) {
            if (File::lastModified($f) < $han) {
                File::delete($f);
                $xoa++;
            }
        }
        $this->info("Đã xoá {$xoa} bản sao lưu cũ.");

        return self::SUCCESS;
    }

    /** @return string|null thông báo lỗi */
    private function taoBan(string $thuMuc): ?string
    {
        $c = config('database.connections.'.config('database.default'));
        $tho = $thuMuc.'/dat_san-'.now()->format('Y-m-d_His').'.sql';

        if (in_array($c['driver'], ['mysql', 'mariadb'], true)) {
            $finder = new ExecutableFinder;
            $bin = $finder->find('mariadb-dump') ?? $finder->find('mysqldump');
            if (! $bin) {
                return 'Không tìm thấy mariadb-dump / mysqldump trong PATH.';
            }
            $p = new Process([
                $bin, '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'],
                '--single-transaction', '--quick', '--routines', '--default-character-set=utf8mb4',
                '--result-file='.$tho, $c['database'],
            ], null, ['MYSQL_PWD' => (string) $c['password']]);
            $p->setTimeout(900)->run();
            if (! $p->isSuccessful()) {
                File::delete($tho);

                return 'Sao lưu lỗi: '.trim($p->getErrorOutput());
            }
        } elseif ($c['driver'] === 'sqlite' && is_file($c['database'])) {
            File::copy($c['database'], $tho);
        } else {
            return 'Không hỗ trợ sao lưu kết nối "'.$c['driver'].'".';
        }

        // Nén gzip theo từng khúc (không nạp cả file vào RAM)
        $vao = fopen($tho, 'rb');
        $ra = gzopen($tho.'.gz', 'wb6');
        while (! feof($vao)) {
            gzwrite($ra, fread($vao, 1024 * 512));
        }
        fclose($vao);
        gzclose($ra);
        File::delete($tho);
        $this->info('Đã sao lưu: '.basename($tho).'.gz ('.round(filesize($tho.'.gz') / 1024).' KB)');

        return null;
    }
}
