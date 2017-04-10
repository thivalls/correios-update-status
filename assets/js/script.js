jQuery(document).ready(function($){
    var successMessage = $('div h2');
    $('.correios-update-status').on('click', function() {
        $.ajax({
            url: ajaxurl,
            type:"POST",
            dataType: "json",
            data: { action: "TwCorreiosUpdateStatusAjax" },
            beforeSend: function () {
                $('.tw-delivery-loading').fadeIn();
            },
            success: function ( response ) {
                if(response.data){
                    $('.tw-delivery-loading').fadeOut();
                    if(response.data.ordersCompleted.length > 0){
                        successMessage.after('<hr><div class="updated"><p>Pedidos Concluídos</p><p>'+response.data.ordersCompleted+'</p></div>');
                    }

                    if(response.data.ordersFailed.length > 0){
                        successMessage.after('<hr><div class="updated"><p>Pedidos com falhas</p><p>'+response.data.ordersFailed+'</p></div>');
                    }

                    if(response.data.ordersCompleted.length == 0 && response.data.ordersFailed.length == 0){
                        successMessage.after('<hr><div class="updated"><p>Nenhum pedido a ser atualizado.</p></div>');
                    }

                }else{
                    successMessage.after('<div class="updated"><p>Nenhum pedido a ser atualizado.</p></div>');
                }
            },
            error: function (error) {
                $('.tw-delivery-loading').fadeOut();
                console.log(error);
                successMessage.after('<div class="error"><p>Erro. Tente novamente.</p></div>');
            }
        });
    });

    // TW Easy Tracking Code

    $('.wc-correios-tracking-field').slideToggle();
    
    $('.wc-correios-tracking').on('click', function(event) {
        event.preventDefault();

        $(this).parent().find('.wc-correios-tracking-field').slideToggle();
        $(this).parent().find('.wc-correios-tracking-field').focus();
    });

    $('.wc-correios-tracking-field').on('keypress', function(event) {

        var order_id = $(this).data('order-id');
        field = $(this);

        if( 13 == event.which ) { //enter
            event.preventDefault();

            $.ajax({
                type:     'POST',
                url:      woocommerce_admin_meta_boxes.ajax_url,
                cache:    false,
                data:     {
                    action:       'TwAddCorreiosTracking',
                    tracking:       $(this).val(),
                    order_id:       order_id,
                },

                beforeSend: function () {
                    field.removeClass('success-save');
                    field.removeClass('error-save');
                },
                success: function ( data ) {

                    if ( 'ok' == data.data.message ) {
                        field.addClass('success-save');
                        field.removeClass('error-save');
                    } else {
                        field.removeClass('success-save');
                        field.addClass('error-save');
                    }

                },
                error: function () {
                    alert('Ocorreu um erro ao processar. Por favor, tente novamente.');
                }

            });

        }

    });
});
