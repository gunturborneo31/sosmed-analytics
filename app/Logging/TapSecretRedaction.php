<?php

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * Memasang {@see RedactsSecrets} pada kanal log.
 *
 * Dipasang lewat `tap` di config/logging.php, bukan di service provider, supaya
 * terlihat langsung oleh siapa pun yang membaca konfigurasi kanalnya — kalau
 * disembunyikan di provider, penyamaran ini gampang terhapus tanpa disadari
 * saat kanal barunya ditambahkan.
 */
class TapSecretRedaction
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(new RedactsSecrets);
    }
}
