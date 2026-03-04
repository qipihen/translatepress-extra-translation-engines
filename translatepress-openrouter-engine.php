<?php
/**
 * Plugin Name: TranslatePress Extra Translation Engines
 * Description: Adds OpenRouter and affordable domestic translation engines to TranslatePress automatic translation.
 * Version: 0.3.0
 * Author: Codex
 * License: GPL2+
 * Text Domain: translatepress-openrouter-engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'TRP_OR_PLUGIN_VERSION' ) ) {
	define( 'TRP_OR_PLUGIN_VERSION', '0.3.0' );
}

if ( ! defined( 'TRP_OR_PLUGIN_DIR' ) ) {
	define( 'TRP_OR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

add_filter( 'trp_machine_translation_engines', 'trp_or_add_engines', 15 );
add_filter( 'trp_automatic_translation_engines_classes', 'trp_or_add_engine_classes', 15 );
add_action( 'trp_machine_translation_extra_settings_middle', 'trp_or_add_settings', 15 );
add_filter( 'trp_machine_translation_sanitize_settings', 'trp_or_sanitize_settings', 15, 2 );
add_filter( 'trp_get_default_trp_machine_translation_settings', 'trp_or_default_mt_settings', 15 );
add_action( 'trp_before_running_hooks', 'trp_or_register_into_tp_loader', 1, 1 );
add_action( 'admin_notices', 'trp_or_admin_debug_notice' );

/**
 * Also register hooks through TranslatePress internal loader for compatibility with older/newer load orders.
 *
 * @param mixed $loader
 */
function trp_or_register_into_tp_loader( $loader ) {
	if ( ! is_object( $loader ) || ! method_exists( $loader, 'add_filter' ) || ! method_exists( $loader, 'add_action' ) ) {
		return;
	}

	$loader->add_filter( 'trp_machine_translation_engines', null, 'trp_or_add_engines', 15, 1 );
	$loader->add_filter( 'trp_automatic_translation_engines_classes', null, 'trp_or_add_engine_classes', 15, 1 );
	$loader->add_action( 'trp_machine_translation_extra_settings_middle', null, 'trp_or_add_settings', 15, 1 );
	$loader->add_filter( 'trp_machine_translation_sanitize_settings', null, 'trp_or_sanitize_settings', 15, 2 );
	$loader->add_filter( 'trp_get_default_trp_machine_translation_settings', null, 'trp_or_default_mt_settings', 15, 1 );
}

/**
 * Optional debug notice for troubleshooting integration visibility.
 * Append "&trp_or_debug=1" on the Automatic Translation page URL to display it.
 */
function trp_or_admin_debug_notice() {
	if ( ! current_user_can( apply_filters( 'trp_settings_capability', 'manage_options' ) ) ) {
		return;
	}

	if ( ! isset( $_GET['page'] ) || 'trp_machine_translation' !== $_GET['page'] ) {
		return;
	}

	if ( ! isset( $_GET['trp_or_debug'] ) || '1' !== (string) $_GET['trp_or_debug'] ) {
		return;
	}

	$engines = apply_filters( 'trp_machine_translation_engines', array() );
	$values  = array();
	foreach ( $engines as $engine ) {
		if ( is_array( $engine ) && ! empty( $engine['value'] ) ) {
			$values[] = (string) $engine['value'];
		}
	}
	$values = array_values( array_unique( $values ) );

	?>
	<div class="notice notice-info">
		<p>
			<?php
			echo esc_html(
				sprintf(
					'[TPOR %1$s] loaded. trp_machine_translation_engines count=%2$d values=%3$s',
					TRP_OR_PLUGIN_VERSION,
					count( $values ),
					implode( ',', $values )
				)
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Load custom machine translator classes only after TranslatePress base MT class is available.
 *
 * @return bool
 */
function trp_or_include_engine_classes() {
	if ( ! class_exists( 'TRP_Machine_Translator' ) ) {
		return false;
	}

	require_once TRP_OR_PLUGIN_DIR . 'includes/class-trp-openrouter-response-parser.php';
	require_once TRP_OR_PLUGIN_DIR . 'includes/class-trp-openrouter-machine-translator.php';
	require_once TRP_OR_PLUGIN_DIR . 'includes/class-trp-transhome-machine-translator.php';
	require_once TRP_OR_PLUGIN_DIR . 'includes/class-trp-youdao-machine-translator.php';
	require_once TRP_OR_PLUGIN_DIR . 'includes/class-trp-baidu-machine-translator.php';
	require_once TRP_OR_PLUGIN_DIR . 'includes/class-trp-tencent-tmt-machine-translator.php';
	require_once TRP_OR_PLUGIN_DIR . 'includes/class-trp-aliyun-machine-translator.php';

	return true;
}

/**
 * Return custom engine definitions.
 *
 * @return array
 */
function trp_or_get_custom_engines() {
	return array(
		array(
			'value' => 'openrouter',
			'label' => __( 'OpenRouter / OpenAI Compatible', 'translatepress-openrouter-engine' ),
			'class' => 'TRP_OpenRouter_Machine_Translator',
		),
		array(
			'value' => 'transhome_translate',
			'label' => __( 'Trans Home Proxy API', 'translatepress-openrouter-engine' ),
			'class' => 'TRP_Transhome_Machine_Translator',
		),
		array(
			'value' => 'youdao_translate',
			'label' => __( 'Youdao Translate API', 'translatepress-openrouter-engine' ),
			'class' => 'TRP_Youdao_Machine_Translator',
		),
		array(
			'value' => 'baidu_translate',
			'label' => __( 'Baidu Translate API', 'translatepress-openrouter-engine' ),
			'class' => 'TRP_Baidu_Machine_Translator',
		),
		array(
			'value' => 'tencent_tmt',
			'label' => __( 'Tencent Cloud TMT', 'translatepress-openrouter-engine' ),
			'class' => 'TRP_Tencent_TMT_Machine_Translator',
		),
		array(
			'value' => 'aliyun_translate',
			'label' => __( 'Aliyun Machine Translation', 'translatepress-openrouter-engine' ),
			'class' => 'TRP_Aliyun_Machine_Translator',
		),
	);
}

/**
 * Register custom engines in TranslatePress selector.
 *
 * @param array $engines
 *
 * @return array
 */
function trp_or_add_engines( $engines ) {
	$by_value = array();

	foreach ( (array) $engines as $engine ) {
		if ( is_array( $engine ) && ! empty( $engine['value'] ) ) {
			$by_value[ (string) $engine['value'] ] = $engine;
		}
	}

	foreach ( trp_or_get_custom_engines() as $engine ) {
		$by_value[ $engine['value'] ] = array(
			'value' => $engine['value'],
			'label' => $engine['label'],
		);
	}

	return array_values( $by_value );
}

/**
 * Register custom engine class mapping.
 *
 * @param array $engines_classes
 *
 * @return array
 */
function trp_or_add_engine_classes( $engines_classes ) {
	if ( ! trp_or_include_engine_classes() ) {
		return $engines_classes;
	}

	foreach ( trp_or_get_custom_engines() as $engine ) {
		$engines_classes[ $engine['value'] ] = $engine['class'];
	}
	return $engines_classes;
}

/**
 * Render settings for all custom engines.
 *
 * @param array $mt_settings
 */
function trp_or_add_settings( $mt_settings ) {
	static $rendered = false;
	if ( $rendered ) {
		return;
	}
	$rendered = true;

	if ( ! class_exists( 'TRP_Translate_Press' ) ) {
		return;
	}

	$trp                = TRP_Translate_Press::get_trp_instance();
	if ( ! is_object( $trp ) || ! method_exists( $trp, 'get_component' ) ) {
		return;
	}

	$machine_translator = $trp->get_component( 'machine_translator' );
	if ( ! is_object( $machine_translator ) ) {
		return;
	}

	trp_or_render_openrouter_settings( $mt_settings, $machine_translator );
	trp_or_render_transhome_settings( $mt_settings, $machine_translator );
	trp_or_render_youdao_settings( $mt_settings, $machine_translator );
	trp_or_render_baidu_settings( $mt_settings, $machine_translator );
	trp_or_render_tencent_settings( $mt_settings, $machine_translator );
	trp_or_render_aliyun_settings( $mt_settings, $machine_translator );
}

/**
 * Render Trans Home proxy settings (single token mode).
 *
 * @param array $mt_settings
 * @param mixed $machine_translator
 */
function trp_or_render_transhome_settings( $mt_settings, $machine_translator ) {
	$translation_engine = isset( $mt_settings['translation-engine'] ) ? $mt_settings['translation-engine'] : '';
	$show_errors        = false;
	$error_message      = '';

	if ( 'transhome_translate' === $translation_engine && method_exists( $machine_translator, 'check_api_key_validity' ) ) {
		$api_check = $machine_translator->check_api_key_validity();
		if ( isset( $api_check['error'] ) && true === $api_check['error'] ) {
			$show_errors   = true;
			$error_message = isset( $api_check['message'] ) ? $api_check['message'] : '';
		}
	}

	$text_input_classes = array( 'trp-text-input' );
	if ( $show_errors && 'transhome_translate' === $translation_engine ) {
		$text_input_classes[] = 'trp-text-input-error';
	}

	$base_url  = isset( $mt_settings['transhome-base-url'] ) ? $mt_settings['transhome-base-url'] : 'https://tb.trans-home.com';
	$api_path  = isset( $mt_settings['transhome-api-path'] ) ? $mt_settings['transhome-api-path'] : '/api/index/translateBatch';
	$mime_type = isset( $mt_settings['transhome-mime-type'] ) ? (string) $mt_settings['transhome-mime-type'] : '0';
	$mime_type = ( '1' === $mime_type ) ? '1' : '0';
	?>
	<div class="trp-engine trp-automatic-translation-engine__container" id="transhome_translate">
		<span class="trp-primary-text-bold"><?php esc_html_e( 'Trans Home Token', 'translatepress-openrouter-engine' ); ?></span>
		<div class="trp-automatic-translation-api-key-container">
			<input type="text"
			       class="<?php echo esc_attr( implode( ' ', $text_input_classes ) ); ?>"
			       name="trp_machine_translation_settings[transhome-token]"
			       placeholder="<?php esc_attr_e( 'Add your token here...', 'translatepress-openrouter-engine' ); ?>"
			       value="<?php echo isset( $mt_settings['transhome-token'] ) ? esc_attr( $mt_settings['transhome-token'] ) : ''; ?>" />
			<?php
			if ( 'transhome_translate' === $translation_engine && method_exists( $machine_translator, 'automatic_translation_svg_output' ) ) {
				$machine_translator->automatic_translation_svg_output( $show_errors );
			}
			?>
		</div>
		<?php if ( $show_errors ) : ?>
			<span class="trp-error-inline trp-settings-error-text"><?php echo esc_html( $error_message ); ?></span>
		<?php endif; ?>
		<span class="trp-description-text">
			<?php esc_html_e( 'For trans-home single-token proxy API. This is not the official Youdao/Baidu AppKey+Secret mode.', 'translatepress-openrouter-engine' ); ?>
		</span>

		<div class="trp-deepl-settings__container" style="margin-top:16px;">
			<span class="trp-primary-text-bold"><?php esc_html_e( 'Base URL', 'translatepress-openrouter-engine' ); ?></span>
			<input type="text" class="trp-text-input" name="trp_machine_translation_settings[transhome-base-url]" value="<?php echo esc_attr( $base_url ); ?>" />
		</div>

		<div class="trp-deepl-settings__container" style="margin-top:16px;">
			<span class="trp-primary-text-bold"><?php esc_html_e( 'Batch API Path', 'translatepress-openrouter-engine' ); ?></span>
			<input type="text" class="trp-text-input" name="trp_machine_translation_settings[transhome-api-path]" value="<?php echo esc_attr( $api_path ); ?>" />
		</div>

		<div class="trp-deepl-settings__container" style="margin-top:16px;">
			<span class="trp-primary-text-bold"><?php esc_html_e( 'Content Type', 'translatepress-openrouter-engine' ); ?></span>
			<select class="trp-text-input" name="trp_machine_translation_settings[transhome-mime-type]">
				<option value="0" <?php selected( $mime_type, '0' ); ?>><?php esc_html_e( 'Plain text', 'translatepress-openrouter-engine' ); ?></option>
				<option value="1" <?php selected( $mime_type, '1' ); ?>><?php esc_html_e( 'HTML (recommended for website content)', 'translatepress-openrouter-engine' ); ?></option>
			</select>
			<span class="trp-description-text">
				<?php esc_html_e( 'Select how strings are parsed before translation.', 'translatepress-openrouter-engine' ); ?>
			</span>
		</div>
	</div>
	<?php
}

/**
 * Render OpenRouter/OpenAI-compatible settings.
 *
 * @param array $mt_settings
 * @param mixed $machine_translator
 */
function trp_or_render_openrouter_settings( $mt_settings, $machine_translator ) {
	$translation_engine = isset( $mt_settings['translation-engine'] ) ? $mt_settings['translation-engine'] : '';
	$show_errors        = false;
	$error_message      = '';

	if ( 'openrouter' === $translation_engine && method_exists( $machine_translator, 'check_api_key_validity' ) ) {
		$api_check = $machine_translator->check_api_key_validity();
		if ( isset( $api_check['error'] ) && true === $api_check['error'] ) {
			$show_errors   = true;
			$error_message = isset( $api_check['message'] ) ? $api_check['message'] : '';
		}
	}

	$text_input_classes = array( 'trp-text-input' );
	if ( $show_errors && 'openrouter' === $translation_engine ) {
		$text_input_classes[] = 'trp-text-input-error';
	}

	$base_url   = isset( $mt_settings['openrouter-base-url'] ) ? $mt_settings['openrouter-base-url'] : 'https://openrouter.ai/api/v1';
	$api_path   = isset( $mt_settings['openrouter-api-path'] ) ? $mt_settings['openrouter-api-path'] : '/chat/completions';
	$model      = isset( $mt_settings['openrouter-model'] ) ? $mt_settings['openrouter-model'] : 'openai/gpt-4o-mini';
	$site_url   = isset( $mt_settings['openrouter-site-url'] ) ? $mt_settings['openrouter-site-url'] : '';
	$site_name  = isset( $mt_settings['openrouter-site-name'] ) ? $mt_settings['openrouter-site-name'] : '';
	$temp       = isset( $mt_settings['openrouter-temperature'] ) ? $mt_settings['openrouter-temperature'] : '0';
	$chunk_size = isset( $mt_settings['openrouter-chunk-size'] ) ? $mt_settings['openrouter-chunk-size'] : 20;
	$whitelist  = isset( $mt_settings['openrouter-language-whitelist'] ) ? $mt_settings['openrouter-language-whitelist'] : '';
	?>
	<div class="trp-engine trp-automatic-translation-engine__container" id="openrouter">
		<span class="trp-primary-text-bold"><?php esc_html_e( 'OpenRouter API Key', 'translatepress-openrouter-engine' ); ?></span>
		<div class="trp-automatic-translation-api-key-container">
			<input type="text"
			       class="<?php echo esc_attr( implode( ' ', $text_input_classes ) ); ?>"
			       name="trp_machine_translation_settings[openrouter-api-key]"
			       placeholder="<?php esc_attr_e( 'Add your API Key here...', 'translatepress-openrouter-engine' ); ?>"
			       value="<?php echo isset( $mt_settings['openrouter-api-key'] ) ? esc_attr( $mt_settings['openrouter-api-key'] ) : ''; ?>" />
			<?php
			if ( 'openrouter' === $translation_engine && method_exists( $machine_translator, 'automatic_translation_svg_output' ) ) {
				$machine_translator->automatic_translation_svg_output( $show_errors );
			}
			?>
		</div>
		<?php if ( $show_errors ) : ?>
			<span class="trp-error-inline trp-settings-error-text"><?php echo esc_html( $error_message ); ?></span>
		<?php endif; ?>
		<span class="trp-description-text">
			<?php esc_html_e( 'Use OpenRouter or any OpenAI-compatible endpoint.', 'translatepress-openrouter-engine' ); ?>
		</span>

		<div class="trp-deepl-settings__container" style="margin-top:16px;">
			<span class="trp-primary-text-bold"><?php esc_html_e( 'Model', 'translatepress-openrouter-engine' ); ?></span>
			<input type="text" class="trp-text-input" name="trp_machine_translation_settings[openrouter-model]" value="<?php echo esc_attr( $model ); ?>" />
		</div>

		<div class="trp-deepl-settings__container" style="margin-top:16px;">
			<span class="trp-primary-text-bold"><?php esc_html_e( 'Base URL', 'translatepress-openrouter-engine' ); ?></span>
			<input type="text" class="trp-text-input" name="trp_machine_translation_settings[openrouter-base-url]" value="<?php echo esc_attr( $base_url ); ?>" />
		</div>

		<div class="trp-deepl-settings__container" style="margin-top:16px;">
			<span class="trp-primary-text-bold"><?php esc_html_e( 'Completions Path', 'translatepress-openrouter-engine' ); ?></span>
			<input type="text" class="trp-text-input" name="trp_machine_translation_settings[openrouter-api-path]" value="<?php echo esc_attr( $api_path ); ?>" />
		</div>

		<div class="trp-deepl-settings__container" style="margin-top:16px;">
			<span class="trp-primary-text-bold"><?php esc_html_e( 'Temperature', 'translatepress-openrouter-engine' ); ?></span>
			<input type="number" step="0.1" min="0" max="2" class="trp-text-input" name="trp_machine_translation_settings[openrouter-temperature]" value="<?php echo esc_attr( $temp ); ?>" />
		</div>

		<div class="trp-deepl-settings__container" style="margin-top:16px;">
			<span class="trp-primary-text-bold"><?php esc_html_e( 'Chunk Size', 'translatepress-openrouter-engine' ); ?></span>
			<input type="number" min="1" max="100" class="trp-text-input" name="trp_machine_translation_settings[openrouter-chunk-size]" value="<?php echo esc_attr( $chunk_size ); ?>" />
		</div>

		<div class="trp-deepl-settings__container" style="margin-top:16px;">
			<span class="trp-primary-text-bold"><?php esc_html_e( 'Language Whitelist (optional)', 'translatepress-openrouter-engine' ); ?></span>
			<input type="text"
			       class="trp-text-input"
			       name="trp_machine_translation_settings[openrouter-language-whitelist]"
			       placeholder="en,zh,zh-tw,ja,ko"
			       value="<?php echo esc_attr( $whitelist ); ?>" />
			<span class="trp-description-text">
				<?php esc_html_e( 'Comma-separated language codes used for TranslatePress language availability checks. Leave empty to allow all TranslatePress languages.', 'translatepress-openrouter-engine' ); ?>
			</span>
		</div>

		<div class="trp-deepl-settings__container" style="margin-top:16px;">
			<span class="trp-primary-text-bold"><?php esc_html_e( 'Site URL (optional)', 'translatepress-openrouter-engine' ); ?></span>
			<input type="text" class="trp-text-input" name="trp_machine_translation_settings[openrouter-site-url]" value="<?php echo esc_attr( $site_url ); ?>" />
		</div>

		<div class="trp-deepl-settings__container" style="margin-top:16px;">
			<span class="trp-primary-text-bold"><?php esc_html_e( 'Site Name (optional)', 'translatepress-openrouter-engine' ); ?></span>
			<input type="text" class="trp-text-input" name="trp_machine_translation_settings[openrouter-site-name]" value="<?php echo esc_attr( $site_name ); ?>" />
		</div>
	</div>
	<?php
}

/**
 * Render Youdao settings.
 *
 * @param array $mt_settings
 * @param mixed $machine_translator
 */
function trp_or_render_youdao_settings( $mt_settings, $machine_translator ) {
	$translation_engine = isset( $mt_settings['translation-engine'] ) ? $mt_settings['translation-engine'] : '';
	$show_errors        = false;
	$error_message      = '';

	if ( 'youdao_translate' === $translation_engine && method_exists( $machine_translator, 'check_api_key_validity' ) ) {
		$api_check = $machine_translator->check_api_key_validity();
		if ( isset( $api_check['error'] ) && true === $api_check['error'] ) {
			$show_errors   = true;
			$error_message = isset( $api_check['message'] ) ? $api_check['message'] : '';
		}
	}
	?>
	<div class="trp-engine trp-automatic-translation-engine__container" id="youdao_translate">
		<span class="trp-primary-text-bold"><?php esc_html_e( 'Youdao AppKey', 'translatepress-openrouter-engine' ); ?></span>
		<div class="trp-automatic-translation-api-key-container">
			<input type="text" class="trp-text-input <?php echo $show_errors ? 'trp-text-input-error' : ''; ?>" name="trp_machine_translation_settings[youdao-app-key]" value="<?php echo isset( $mt_settings['youdao-app-key'] ) ? esc_attr( $mt_settings['youdao-app-key'] ) : ''; ?>" />
			<?php if ( 'youdao_translate' === $translation_engine && method_exists( $machine_translator, 'automatic_translation_svg_output' ) ) { $machine_translator->automatic_translation_svg_output( $show_errors ); } ?>
		</div>
		<span class="trp-primary-text-bold" style="display:block;margin-top:12px;"><?php esc_html_e( 'Youdao AppSecret', 'translatepress-openrouter-engine' ); ?></span>
		<input type="text" class="trp-text-input <?php echo $show_errors ? 'trp-text-input-error' : ''; ?>" name="trp_machine_translation_settings[youdao-app-secret]" value="<?php echo isset( $mt_settings['youdao-app-secret'] ) ? esc_attr( $mt_settings['youdao-app-secret'] ) : ''; ?>" />
		<?php if ( $show_errors ) : ?><span class="trp-error-inline trp-settings-error-text"><?php echo esc_html( $error_message ); ?></span><?php endif; ?>
		<span class="trp-description-text"><?php esc_html_e( 'Youdao API endpoint: https://openapi.youdao.com/api', 'translatepress-openrouter-engine' ); ?></span>
	</div>
	<?php
}

/**
 * Render Baidu settings.
 *
 * @param array $mt_settings
 * @param mixed $machine_translator
 */
function trp_or_render_baidu_settings( $mt_settings, $machine_translator ) {
	$translation_engine = isset( $mt_settings['translation-engine'] ) ? $mt_settings['translation-engine'] : '';
	$show_errors        = false;
	$error_message      = '';

	if ( 'baidu_translate' === $translation_engine && method_exists( $machine_translator, 'check_api_key_validity' ) ) {
		$api_check = $machine_translator->check_api_key_validity();
		if ( isset( $api_check['error'] ) && true === $api_check['error'] ) {
			$show_errors   = true;
			$error_message = isset( $api_check['message'] ) ? $api_check['message'] : '';
		}
	}
	?>
	<div class="trp-engine trp-automatic-translation-engine__container" id="baidu_translate">
		<span class="trp-primary-text-bold"><?php esc_html_e( 'Baidu AppID', 'translatepress-openrouter-engine' ); ?></span>
		<div class="trp-automatic-translation-api-key-container">
			<input type="text" class="trp-text-input <?php echo $show_errors ? 'trp-text-input-error' : ''; ?>" name="trp_machine_translation_settings[baidu-app-id]" value="<?php echo isset( $mt_settings['baidu-app-id'] ) ? esc_attr( $mt_settings['baidu-app-id'] ) : ''; ?>" />
			<?php if ( 'baidu_translate' === $translation_engine && method_exists( $machine_translator, 'automatic_translation_svg_output' ) ) { $machine_translator->automatic_translation_svg_output( $show_errors ); } ?>
		</div>
		<span class="trp-primary-text-bold" style="display:block;margin-top:12px;"><?php esc_html_e( 'Baidu AppSecret', 'translatepress-openrouter-engine' ); ?></span>
		<input type="text" class="trp-text-input <?php echo $show_errors ? 'trp-text-input-error' : ''; ?>" name="trp_machine_translation_settings[baidu-app-secret]" value="<?php echo isset( $mt_settings['baidu-app-secret'] ) ? esc_attr( $mt_settings['baidu-app-secret'] ) : ''; ?>" />
		<?php if ( $show_errors ) : ?><span class="trp-error-inline trp-settings-error-text"><?php echo esc_html( $error_message ); ?></span><?php endif; ?>
		<span class="trp-description-text"><?php esc_html_e( 'Baidu API endpoint: https://fanyi-api.baidu.com/api/trans/vip/translate', 'translatepress-openrouter-engine' ); ?></span>
	</div>
	<?php
}

/**
 * Render Tencent TMT settings.
 *
 * @param array $mt_settings
 * @param mixed $machine_translator
 */
function trp_or_render_tencent_settings( $mt_settings, $machine_translator ) {
	$translation_engine = isset( $mt_settings['translation-engine'] ) ? $mt_settings['translation-engine'] : '';
	$show_errors        = false;
	$error_message      = '';

	if ( 'tencent_tmt' === $translation_engine && method_exists( $machine_translator, 'check_api_key_validity' ) ) {
		$api_check = $machine_translator->check_api_key_validity();
		if ( isset( $api_check['error'] ) && true === $api_check['error'] ) {
			$show_errors   = true;
			$error_message = isset( $api_check['message'] ) ? $api_check['message'] : '';
		}
	}
	?>
	<div class="trp-engine trp-automatic-translation-engine__container" id="tencent_tmt">
		<span class="trp-primary-text-bold"><?php esc_html_e( 'Tencent SecretId', 'translatepress-openrouter-engine' ); ?></span>
		<div class="trp-automatic-translation-api-key-container">
			<input type="text" class="trp-text-input <?php echo $show_errors ? 'trp-text-input-error' : ''; ?>" name="trp_machine_translation_settings[tencent-secret-id]" value="<?php echo isset( $mt_settings['tencent-secret-id'] ) ? esc_attr( $mt_settings['tencent-secret-id'] ) : ''; ?>" />
			<?php if ( 'tencent_tmt' === $translation_engine && method_exists( $machine_translator, 'automatic_translation_svg_output' ) ) { $machine_translator->automatic_translation_svg_output( $show_errors ); } ?>
		</div>
		<span class="trp-primary-text-bold" style="display:block;margin-top:12px;"><?php esc_html_e( 'Tencent SecretKey', 'translatepress-openrouter-engine' ); ?></span>
		<input type="text" class="trp-text-input <?php echo $show_errors ? 'trp-text-input-error' : ''; ?>" name="trp_machine_translation_settings[tencent-secret-key]" value="<?php echo isset( $mt_settings['tencent-secret-key'] ) ? esc_attr( $mt_settings['tencent-secret-key'] ) : ''; ?>" />
		<span class="trp-primary-text-bold" style="display:block;margin-top:12px;"><?php esc_html_e( 'Region', 'translatepress-openrouter-engine' ); ?></span>
		<input type="text" class="trp-text-input" name="trp_machine_translation_settings[tencent-region]" value="<?php echo isset( $mt_settings['tencent-region'] ) ? esc_attr( $mt_settings['tencent-region'] ) : 'ap-guangzhou'; ?>" />
		<span class="trp-primary-text-bold" style="display:block;margin-top:12px;"><?php esc_html_e( 'ProjectId (optional)', 'translatepress-openrouter-engine' ); ?></span>
		<input type="number" min="0" class="trp-text-input" name="trp_machine_translation_settings[tencent-project-id]" value="<?php echo isset( $mt_settings['tencent-project-id'] ) ? esc_attr( $mt_settings['tencent-project-id'] ) : '0'; ?>" />
		<span class="trp-primary-text-bold" style="display:block;margin-top:12px;"><?php esc_html_e( 'Session Token (optional)', 'translatepress-openrouter-engine' ); ?></span>
		<input type="text" class="trp-text-input" name="trp_machine_translation_settings[tencent-token]" value="<?php echo isset( $mt_settings['tencent-token'] ) ? esc_attr( $mt_settings['tencent-token'] ) : ''; ?>" />
		<?php if ( $show_errors ) : ?><span class="trp-error-inline trp-settings-error-text"><?php echo esc_html( $error_message ); ?></span><?php endif; ?>
	</div>
	<?php
}

/**
 * Render Aliyun settings.
 *
 * @param array $mt_settings
 * @param mixed $machine_translator
 */
function trp_or_render_aliyun_settings( $mt_settings, $machine_translator ) {
	$translation_engine = isset( $mt_settings['translation-engine'] ) ? $mt_settings['translation-engine'] : '';
	$show_errors        = false;
	$error_message      = '';

	if ( 'aliyun_translate' === $translation_engine && method_exists( $machine_translator, 'check_api_key_validity' ) ) {
		$api_check = $machine_translator->check_api_key_validity();
		if ( isset( $api_check['error'] ) && true === $api_check['error'] ) {
			$show_errors   = true;
			$error_message = isset( $api_check['message'] ) ? $api_check['message'] : '';
		}
	}
	?>
	<div class="trp-engine trp-automatic-translation-engine__container" id="aliyun_translate">
		<span class="trp-primary-text-bold"><?php esc_html_e( 'Aliyun AccessKeyId', 'translatepress-openrouter-engine' ); ?></span>
		<div class="trp-automatic-translation-api-key-container">
			<input type="text" class="trp-text-input <?php echo $show_errors ? 'trp-text-input-error' : ''; ?>" name="trp_machine_translation_settings[aliyun-access-key-id]" value="<?php echo isset( $mt_settings['aliyun-access-key-id'] ) ? esc_attr( $mt_settings['aliyun-access-key-id'] ) : ''; ?>" />
			<?php if ( 'aliyun_translate' === $translation_engine && method_exists( $machine_translator, 'automatic_translation_svg_output' ) ) { $machine_translator->automatic_translation_svg_output( $show_errors ); } ?>
		</div>
		<span class="trp-primary-text-bold" style="display:block;margin-top:12px;"><?php esc_html_e( 'Aliyun AccessKeySecret', 'translatepress-openrouter-engine' ); ?></span>
		<input type="text" class="trp-text-input <?php echo $show_errors ? 'trp-text-input-error' : ''; ?>" name="trp_machine_translation_settings[aliyun-access-key-secret]" value="<?php echo isset( $mt_settings['aliyun-access-key-secret'] ) ? esc_attr( $mt_settings['aliyun-access-key-secret'] ) : ''; ?>" />
		<span class="trp-primary-text-bold" style="display:block;margin-top:12px;"><?php esc_html_e( 'RegionId', 'translatepress-openrouter-engine' ); ?></span>
		<input type="text" class="trp-text-input" name="trp_machine_translation_settings[aliyun-region-id]" value="<?php echo isset( $mt_settings['aliyun-region-id'] ) ? esc_attr( $mt_settings['aliyun-region-id'] ) : 'cn-hangzhou'; ?>" />
		<span class="trp-primary-text-bold" style="display:block;margin-top:12px;"><?php esc_html_e( 'Scene', 'translatepress-openrouter-engine' ); ?></span>
		<input type="text" class="trp-text-input" name="trp_machine_translation_settings[aliyun-scene]" value="<?php echo isset( $mt_settings['aliyun-scene'] ) ? esc_attr( $mt_settings['aliyun-scene'] ) : 'general'; ?>" />
		<?php if ( $show_errors ) : ?><span class="trp-error-inline trp-settings-error-text"><?php echo esc_html( $error_message ); ?></span><?php endif; ?>
		<span class="trp-description-text"><?php esc_html_e( 'Aliyun endpoint pattern: https://mt.<region>.aliyuncs.com/', 'translatepress-openrouter-engine' ); ?></span>
	</div>
	<?php
}

/**
 * Sanitize custom settings.
 *
 * @param array $settings
 * @param array $raw_mt_settings
 *
 * @return array
 */
function trp_or_sanitize_settings( $settings, $raw_mt_settings ) {
	$keys = array(
		'openrouter-api-key'      => 'text',
		'openrouter-model'        => 'text',
		'openrouter-base-url'     => 'url',
		'openrouter-api-path'     => 'path',
		'openrouter-site-url'     => 'url',
		'openrouter-site-name'    => 'text',
		'openrouter-temperature'  => 'float',
		'openrouter-chunk-size'   => 'int',
		'openrouter-language-whitelist' => 'lang_csv',
		'transhome-token'         => 'text',
		'transhome-base-url'      => 'url',
		'transhome-api-path'      => 'path',
		'transhome-mime-type'     => 'int',
		'youdao-app-key'          => 'text',
		'youdao-app-secret'       => 'text',
		'baidu-app-id'            => 'text',
		'baidu-app-secret'        => 'text',
		'tencent-secret-id'       => 'text',
		'tencent-secret-key'      => 'text',
		'tencent-region'          => 'text',
		'tencent-token'           => 'text',
		'tencent-project-id'      => 'int',
		'aliyun-access-key-id'    => 'text',
		'aliyun-access-key-secret'=> 'text',
		'aliyun-region-id'        => 'text',
		'aliyun-scene'            => 'text',
	);

	foreach ( $keys as $key => $type ) {
		if ( ! isset( $raw_mt_settings[ $key ] ) ) {
			continue;
		}

		switch ( $type ) {
			case 'url':
				$settings[ $key ] = esc_url_raw( $raw_mt_settings[ $key ] );
				break;
			case 'path':
				$value            = sanitize_text_field( $raw_mt_settings[ $key ] );
				$settings[ $key ] = '/' . ltrim( $value, '/' );
				break;
			case 'float':
				$value            = (float) $raw_mt_settings[ $key ];
				$value            = max( 0, min( 2, $value ) );
				$settings[ $key ] = (string) $value;
				break;
			case 'int':
				$settings[ $key ] = absint( $raw_mt_settings[ $key ] );
				break;
			case 'lang_csv':
				$settings[ $key ] = trp_or_normalize_language_code_list( $raw_mt_settings[ $key ] );
				break;
			default:
				$settings[ $key ] = sanitize_text_field( $raw_mt_settings[ $key ] );
				break;
		}
	}

	return $settings;
}

/**
 * Normalize a user-entered list of language codes.
 *
 * @param string $raw_value
 *
 * @return string
 */
function trp_or_normalize_language_code_list( $raw_value ) {
	$raw_value = sanitize_text_field( (string) $raw_value );
	if ( '' === trim( $raw_value ) ) {
		return '';
	}

	$parts = preg_split( '/[\s,;|]+/', $raw_value );
	if ( ! is_array( $parts ) ) {
		return '';
	}

	$codes = array();
	foreach ( $parts as $part ) {
		$code = strtolower( str_replace( '_', '-', trim( (string) $part ) ) );
		$code = preg_replace( '/[^a-z0-9-]/', '', $code );
		if ( '' !== $code ) {
			$codes[] = $code;
		}
	}

	$codes = array_values( array_unique( $codes ) );

	return implode( ',', $codes );
}

/**
 * Register defaults for custom settings.
 *
 * @param array $defaults
 *
 * @return array
 */
function trp_or_default_mt_settings( $defaults ) {
	$custom_defaults = array(
		'openrouter-model'         => 'openai/gpt-4o-mini',
		'openrouter-base-url'      => 'https://openrouter.ai/api/v1',
		'openrouter-api-path'      => '/chat/completions',
		'openrouter-temperature'   => '0',
		'openrouter-chunk-size'    => 20,
		'openrouter-language-whitelist' => '',
		'transhome-token'          => '',
		'transhome-base-url'       => 'https://tb.trans-home.com',
		'transhome-api-path'       => '/api/index/translateBatch',
		'transhome-mime-type'      => 0,
		'openrouter-site-url'      => '',
		'openrouter-site-name'     => '',
		'youdao-app-key'           => '',
		'youdao-app-secret'        => '',
		'baidu-app-id'             => '',
		'baidu-app-secret'         => '',
		'tencent-secret-id'        => '',
		'tencent-secret-key'       => '',
		'tencent-region'           => 'ap-guangzhou',
		'tencent-project-id'       => 0,
		'tencent-token'            => '',
		'aliyun-access-key-id'     => '',
		'aliyun-access-key-secret' => '',
		'aliyun-region-id'         => 'cn-hangzhou',
		'aliyun-scene'             => 'general',
	);

	foreach ( $custom_defaults as $key => $value ) {
		if ( ! isset( $defaults[ $key ] ) ) {
			$defaults[ $key ] = $value;
		}
	}

	return $defaults;
}

/**
 * Returns a generic error message for OpenRouter/OpenAI-compatible responses.
 *
 * @param int    $code
 * @param string $response_body
 *
 * @return array
 */
function trp_or_response_codes( $code, $response_body = '' ) {
	$is_error       = false;
	$return_message = '';
	$code           = (int) $code;

	if ( preg_match( '/4\d\d/', (string) $code ) || preg_match( '/5\d\d/', (string) $code ) ) {
		$is_error = true;
	}

	$remote_message = '';
	if ( ! empty( $response_body ) ) {
		$decoded = json_decode( $response_body, true );
		if ( is_array( $decoded ) ) {
			if ( ! empty( $decoded['error']['message'] ) ) {
				$remote_message = (string) $decoded['error']['message'];
			} elseif ( ! empty( $decoded['message'] ) ) {
				$remote_message = (string) $decoded['message'];
			}
		}
	}

	if ( $is_error ) {
		switch ( $code ) {
			case 400:
				$return_message = __( 'Bad request. Please verify model, endpoint, and payload format.', 'translatepress-openrouter-engine' );
				break;
			case 401:
			case 403:
				$return_message = __( 'Authentication failed. Please check your API key and endpoint permissions.', 'translatepress-openrouter-engine' );
				break;
			case 404:
				$return_message = __( 'Endpoint or model not found. Please verify Base URL, API path, and model.', 'translatepress-openrouter-engine' );
				break;
			case 429:
				$return_message = __( 'Rate limit reached. Please retry later or reduce request frequency.', 'translatepress-openrouter-engine' );
				break;
			default:
				$return_message = __( 'The translation provider returned an error.', 'translatepress-openrouter-engine' );
				break;
		}

		if ( ! empty( $remote_message ) ) {
			$return_message .= ' ' . $remote_message;
		}
	}

	return array(
		'message' => $return_message,
		'error'   => $is_error,
	);
}
