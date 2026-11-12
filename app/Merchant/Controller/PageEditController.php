<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

/**
 * 商户后台 - page_edit 弹窗（/user/merchant/page_edit）。
 */
final class PageEditController extends MerchantBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $currentMerchant = $this->merchant();
        $merchantLevel = $this->level();
        $merchantId = (int) $currentMerchant['id'];
        $action = (string) ($this->input->post('_action', '') ?: $this->input->get('_action', ''));

        // 保存
        if ($action === 'save') {
            if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                $this->response->error('请求已失效，请刷新页面后重试');
            }

            $id = (int) $this->input->post('id', 0);
            $title = trim((string) $this->input->post('title', ''));
            $slug = trim((string) $this->input->post('slug', ''));
            $content = (string) $this->input->post('content', '');
            $status = (int) $this->input->post('status', 1) === 1 ? 1 : 0;
            $templateName = trim((string) $this->input->post('template_name', ''));
            $sort = (int) $this->input->post('sort', 100);
            $seoTitle = trim((string) $this->input->post('seo_title', ''));
            $seoKeywords = trim((string) $this->input->post('seo_keywords', ''));
            $seoDescription = trim((string) $this->input->post('seo_description', ''));

            if ($title === '') $this->response->error('页面标题不能为空');
            if (mb_strlen($title) > 200) $this->response->error('标题最多 200 字符');

            if ($slug === '') {
                $slug = strtolower($title);
                $slug = preg_replace('/[^a-z0-9\-]+/u', '-', $slug);
                $slug = trim($slug, '-');
                if ($slug === '') {
                    $slug = 'page-' . time();
                }
            } else {
                if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $slug)) {
                    $this->response->error('URL 别名只能包含字母、数字、中划线和下划线');
                }
                $slug = strtolower($slug);
            }

            // 保留字黑名单（避免和系统路由冲突）
            $reserved = ['admin', 'user', 'blog', 'goods', 'order', 'login', 'logout', 'api', 'install', 'content', 'p'];
            if (in_array($slug, $reserved, true)) {
                $this->response->error('URL 别名 "' . $slug . '" 是系统保留字，请换一个');
            }
            if (\App\Model\PageModel::slugExists($slug, $id, $merchantId)) {
                $this->response->error('URL 别名 "' . $slug . '" 在本店已被占用');
            }
            if ($templateName !== '' && !preg_match('/^[a-z0-9_\-]+$/', $templateName)) {
                $this->response->error('模板名只能包含小写字母、数字、中划线和下划线');
            }

            $data = [
                'title'           => $title,
                'slug'            => $slug,
                'content'         => $content,
                'status'          => $status,
                'template_name'   => $templateName,
                'sort'            => $sort,
                'seo_title'       => $seoTitle,
                'seo_keywords'    => $seoKeywords,
                'seo_description' => $seoDescription,
            ];

            try {
                if ($id) {
                    $existing = \App\Model\PageModel::getById($id);
                    if (!$existing || (int) $existing['merchant_id'] !== $merchantId) {
                        $this->response->error('页面不存在');
                    }
                    \App\Model\PageModel::update($id, $data);
                    $pageId = $id;
                } else {
                    $data['merchant_id'] = $merchantId;
                    $pageId = \App\Model\PageModel::create($data);
                }
                if (!$pageId) $this->response->error('保存失败');
            } catch (\Throwable $e) {
                $this->response->error('保存失败：' . $e->getMessage());
            }

            $this->response->success('保存成功', ['id' => $pageId, 'csrf_token' => $this->csrf->refresh()]);
        }

        // 渲染弹窗
        $id = (int) $this->input->get('id', 0);
        $pageRow = $id ? \App\Model\PageModel::getById($id) : null;
        if ($pageRow && (int) $pageRow['merchant_id'] !== $merchantId) {
            $pageRow = null;
        }

        if ($this->input->get('_popup', '') === '1') {
            $this->render('popup/page_edit', get_defined_vars());
            return;
        }
    }
}
