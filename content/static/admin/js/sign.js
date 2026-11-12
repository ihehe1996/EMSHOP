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

        // 保留当前 URL 的查询串，避免登录后的跳转参数丢失
        $.ajax({
            url: (window.EM_ADMIN_PATH || '/admin') + '/sign' + window.location.search,
            type: 'POST',
            dataType: 'json',
            data: $.param(formData),
        }).done(function (res) {
            if (res.code === 200) {
                layui.layer.msg(res.msg || '登录成功');
                window.location.href = res.data.redirect || ((window.EM_ADMIN_PATH || '/admin') + '/home');
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
