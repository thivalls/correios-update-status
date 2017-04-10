<?php
function TwCorreiosUpdateStatusPageRegister(){
    add_menu_page(
        __('Sincronizar Pedidos','correios-update-status'),
        __('Delivery Checkout','correios-update-status'),
        'manage_options',
        'tw-correios-update-status',
        'TwCorreiosUpdateStatusPage',
        '',
        100
    );
}
add_action( 'admin_menu', 'TwCorreiosUpdateStatusPageRegister' );

function TwCorreiosUpdateStatusPage()
{
    ?>
    <div class="wrap">
        <h2>
            <?php _e('Sincronizar entrega dos correios!','correios-update-status'); ?>
            <span class="tw-delivery-loading">
                <img src="<?= get_site_url(); ?>/wp-admin/images/spinner.gif" alt="">
            </span>
        </h2>

        <p>
            <?php _e('Clicando no botão abaixo você estará sincronizando todos os pedidos que já foram entregues, de acordo com consulta a base de dados dos correios.','woocommerce-correios-update-status'); ?>
        </p>
        <small>
            <?php _e('Lembrando que o processo só será aplicado aos pedidos que tiverem o Código de Rastreamento preenchido','woocommerce-correios-update-status');?>
        </small>
        <div class="button-us-page">
            <a class="correios-update-status button button-primary button-large" href="#">
                <?php _e('Checkout','correios-update-status');?>
            </a>
        </div>
    </div>
    <?php
}