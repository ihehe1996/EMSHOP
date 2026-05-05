<?php

declare(strict_types=1);

/**
 * 文件上传服务。
 *
 * 默认存储到本地 /content/uploads/Ymd/ 目录。
 * 上传成功后自动写入 em_attachment 记录，并支持按 MD5 去重。
 * 插件可通过 addFilter('upload_file_result', callback) 钩子替换上传结果（如转存到 OSS）。
 */
final class UploadService
{
    /** @var int 默认最大文件大小 2MB */
    private $maxSize = 2 * 1024 * 1024;

    /**
     * 上传文件。
     *
     * @param array<string, mixed> $file       $_FILES 中的单个文件
     * @param string[]             $allowedExts 允许的扩展名
     * @param string               $context     使用场景：avatar/article/product/order/default
     * @param int|null             $contextId   关联记录ID
     * @return array{url: string, path: string, name: string, attachment_id: int, is_duplicate: bool}
     */
    public function upload(array $file, array $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'], string $context = 'default', ?int $contextId = null): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('未接收到上传文件');
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('文件上传失败，错误码：' . $file['error']);
        }

        if ((int) $file['size'] > $this->maxSize) {
            throw new RuntimeException('文件大小超过限制（最大 ' . ($this->maxSize / 1024 / 1024) . 'MB）');
        }

        $originalName = (string) $file['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts, true)) {
            throw new RuntimeException('不支持的文件格式，仅允许：' . implode('、', $allowedExts));
        }

        // 计算 MD5 用于去重
        $md5 = md5_file($file['tmp_name']);
        $mimeType = (string) $file['type'];

        // 查询是否已存在相同 MD5 的附件（仅在 default/avatar 场景去重）
        $isDuplicate = false;
        $attachmentId = 0;
        $resultUrl = '';

        if (in_array($context, ['default', 'avatar'], true)) {
            $attachmentModel = new AttachmentModel();
            $existing = $attachmentModel->findByMd5($md5);
            if ($existing !== null) {
                $isDuplicate = true;
                $attachmentId = (int) $existing['id'];
                $resultUrl = $existing['file_url'];
            }
        }

        // 无重复时执行实际上传
        if (!$isDuplicate) {
            $dateDir = date('Ymd');
            $uploadDir = EM_ROOT . '/content/uploads/' . $dateDir;

            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new RuntimeException('上传目录创建失败');
                }
            }

            $newName = bin2hex(random_bytes(16)) . '.' . $ext;
            $destPath = $uploadDir . '/' . $newName;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                throw new RuntimeException('文件保存失败');
            }

            $relativePath = '/content/uploads/' . $dateDir . '/' . $newName;
            $resultUrl = $relativePath;

            // 写入附件记录
            $attachmentModel = new AttachmentModel();
            $attachmentId = $attachmentModel->insert([
                'user_id' => $GLOBALS['adminUser']['id'] ?? null,
                'file_name' => $originalName,
                'file_path' => $relativePath,
                'file_url' => $resultUrl,
                'file_size' => (int) $file['size'],
                'file_ext' => $ext,
                'mime_type' => $mimeType,
                'md5' => $md5,
                'driver' => 'local',
                'context' => $context,
                'context_id' => $contextId,
            ]);
        }

        $result = [
            'url' => $resultUrl,
            'path' => EM_ROOT . $resultUrl,
            'name' => $originalName,
            'attachment_id' => $attachmentId,
            'is_duplicate' => $isDuplicate,
        ];

        return applyFilter('upload_file_result', $result);
    }
}
