<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;

class BackupService
{
    private S3Client $s3;

    public function __construct()
    {
        // Uses IAM Role credentials automatically via EC2 Instance Metadata Service.
        // No access keys needed — just configure the IAM Role on the EC2 instance.
        $this->s3 = new S3Client([
            'version' => 'latest',
            'region' => config('filesystems.disks.s3.region', env('AWS_DEFAULT_REGION', 'us-east-1')),
        ]);
    }

    /**
     * Stream a readable resource (e.g. stdout of mysqldump) to S3 via Multipart Upload.
     * No data is written to disk — chunks are piped directly from mysqldump to S3.
     *
     * @param resource $stream   The readable stream (e.g. proc_open stdout).
     * @param string   $bucket   S3 bucket name.
     * @param string   $s3Key    Object key (path inside the bucket).
     * @param int      $partSize Part size in bytes (min 5 MB for multipart uploads).
     *
     * @return array{key: string, size: int} Upload result metadata.
     * @throws \RuntimeException On any S3 or stream error.
     */
    public function streamToS3($stream, string $bucket, string $s3Key, int $partSize = 5 * 1024 * 1024): array
    {
        Log::info("[BackupService] Starting multipart upload → s3://{$bucket}/{$s3Key}");

        $multipart = $this->s3->createMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $s3Key,
            'ContentType' => 'application/gzip',
        ]);

        $uploadId = $multipart['UploadId'];
        $parts = [];
        $partNumber = 1;
        $buffer = '';
        $totalBytes = 0;

        try {
            while (!feof($stream)) {
                $chunk = fread($stream, $partSize);
                if ($chunk === false)
                    break;

                $buffer .= $chunk;
                $totalBytes += strlen($chunk);

                // Once we have a full part (or on final flush), upload it
                if (strlen($buffer) >= $partSize) {
                    $parts[] = $this->uploadPart($bucket, $s3Key, $uploadId, $partNumber, $buffer);
                    $partNumber++;
                    $buffer = '';
                }
            }

            // Upload remaining buffer (last part, which may be smaller than $partSize)
            if (strlen($buffer) > 0) {
                $parts[] = $this->uploadPart($bucket, $s3Key, $uploadId, $partNumber, $buffer);
            }

            $this->s3->completeMultipartUpload([
                'Bucket' => $bucket,
                'Key' => $s3Key,
                'UploadId' => $uploadId,
                'MultipartUpload' => ['Parts' => $parts],
            ]);

            Log::info("[BackupService] Multipart upload completed. Key: {$s3Key}, Size: {$totalBytes} bytes.");

            return ['key' => $s3Key, 'size' => $totalBytes];

        } catch (\Throwable $e) {
            // Abort the multipart upload to avoid leaving incomplete uploads on S3
            $this->s3->abortMultipartUpload([
                'Bucket' => $bucket,
                'Key' => $s3Key,
                'UploadId' => $uploadId,
            ]);

            Log::error("[BackupService] Multipart upload aborted due to error: " . $e->getMessage());
            throw new \RuntimeException("S3 multipart upload failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate a temporary pre-signed URL for downloading a backup file.
     */
    public function getPresignedUrl(string $bucket, string $s3Key, int $expiresInSeconds = 300): string
    {
        $cmd = $this->s3->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key' => $s3Key,
        ]);

        $request = $this->s3->createPresignedRequest($cmd, "+{$expiresInSeconds} seconds");
        return (string) $request->getUri();
    }

    // -------------------------------------------------------------------------

    private function uploadPart(string $bucket, string $key, string $uploadId, int $partNumber, string $body): array
    {
        $result = $this->s3->uploadPart([
            'Bucket' => $bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
            'Body' => $body,
        ]);

        return [
            'PartNumber' => $partNumber,
            'ETag' => $result['ETag'],
        ];
    }
}
