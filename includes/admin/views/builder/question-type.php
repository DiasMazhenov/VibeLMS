<?php
/**
 * Builder quiz type template
 *
 * @since   3.16.0
 * @version 3.27.0
 */
defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-llms-question-type-template">

	<# if ( data.get( 'upgrade' ) ) { #>
		<span class="llms-type-unavailable tip--top-right" data-tip="<?php esc_attr_e( 'Этот тип вопроса недоступен в текущей конфигурации.', 'lifterlms' ); ?>">
	<# } #>
	<button class="llms-element-button small llms-add-question" data-id="{{{ data.get( 'id' ) }}}" id="llms-add-question--{{{ data.get( 'id' ) }}}">
		<i class="fa fa-{{{ data.get( 'icon' ) }}}" aria-hidden="true"></i> {{{ data.get( 'name' ) }}}
	</button>
	<# if ( data.get( 'upgrade' ) ) { #>
		</span>
	<# } #>
</script>
