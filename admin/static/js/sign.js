$(function () {
    $('#loginForm').on('submit', function (event) {
        event.preventDefault();

        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const formData = $form.serializeArray();
        const hasRemember = $form.find('input[name="remember"]').is(':checked');

        if (!hasRemember) {
            formData.push({ name: 'remember', value: '0' });
        }

        $button.prop('disabled', true).addClass('layui-btn-disabled').text('登录中...');

        // 保留当前 URL 的查询串（含安全入口 ?s=xxx），否则 POST 会落到不带参数的 URL 被拦截
        $.ajax({
            url: '/admin/sign.php' + window.location.search,
            type: 'POST',
            dataType: 'json',
            data: $.param(formData),
        }).done(function (res) {
            if (res.code === 200) {
                layui.layer.msg(res.msg || '登录成功');
                window.location.href = res.data.redirect || '/admin/index.php';
                return;
            }

            layui.layer.msg(res.msg || '登录失败');
        }).fail(function () {
            layui.layer.msg('请求失败，请稍后重试');
        }).always(function () {
            $button.prop('disabled', false).removeClass('layui-btn-disabled').text('立即登录');
        });
    });
});
