<?php

namespace App\Jobs;

use App\Models\DigitalAccess;
use App\Models\ProductFile;
use App\Notifications\ProductFileUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Emails every past buyer of a file that a new edition is available.
 *
 * Chunked and queued: a popular product can have thousands of buyers, and none
 * of that belongs in the request where the creator pressed "Ganti file".
 */
class NotifyFileUpdate implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $productFileId) {}

    public function handle(): void
    {
        $file = ProductFile::with('product')->find($this->productFileId);

        if (! $file || ! $file->product) {
            return;
        }

        $productName = $file->product->name;

        DigitalAccess::with('order')
            ->where('product_file_id', $file->id)
            ->where('is_revoked', false)
            ->chunkById(200, function ($accesses) use ($file, $productName) {
                foreach ($accesses as $access) {
                    $order = $access->order;

                    if (! $order || ! $order->customer_email) {
                        continue;
                    }

                    Notification::route('mail', $order->customer_email)
                        ->notify(new ProductFileUpdated($order, $file, $productName));
                }
            });
    }
}
