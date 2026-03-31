<?php

/**
 * Forms Plugin — Settings API Registration
 *
 * Registers the global Forms settings page, sections, and fields through
 * the MinimalCMS Settings API. Accessible at mc-admin/settings.php?page=forms.
 *
 * @package MinimalCMS\Forms
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Register the Forms settings page, sections, and fields.
 *
 * Called from the mc_register_settings hook in the main plugin file.
 *
 * @since 1.0.0
 * @return void
 */
function forms_register_settings_page(): void
{

	mc_register_settings_page(
		'forms',
		array(
			'title'         => 'Forms Settings',
			'capability'    => 'manage_settings',
			'namespace'     => 'plugin.forms',
			'nonce_action'  => 'save_forms_settings',
			'menu_title'    => 'Forms Settings',
			'menu_icon'     => '&#x2699;',
			'menu_position' => 45,
		)
	);

	/*
	 * Section: Notification Defaults
	 */
	mc_register_settings_section(
		'forms',
		'notifications',
		array(
			'title'       => 'Notification Defaults',
			'description' => 'Default notification settings applied to new forms.',
			'priority'    => 10,
		)
	);

	mc_register_setting_field(
		'forms',
		'notifications',
		'default_to_email',
		array(
			'type'        => 'text',
			'label'       => 'Default Recipient Email',
			'description' => 'Email address that receives form submissions by default.',
			'default'     => '',
			'attributes'  => array( 'placeholder' => 'admin@example.com' ),
		)
	);

	mc_register_setting_field(
		'forms',
		'notifications',
		'default_from_name',
		array(
			'type'       => 'text',
			'label'      => 'Default "From" Name',
			'default'    => '',
			'attributes' => array( 'placeholder' => 'Site Name' ),
		)
	);

	mc_register_setting_field(
		'forms',
		'notifications',
		'default_from_email',
		array(
			'type'       => 'text',
			'label'      => 'Default "From" Email',
			'default'    => '',
			'attributes' => array( 'placeholder' => 'noreply@example.com' ),
		)
	);

	mc_register_setting_field(
		'forms',
		'notifications',
		'default_subject',
		array(
			'type'        => 'text',
			'label'       => 'Default Email Subject',
			'description' => 'Use {form_title} as a placeholder.',
			'default'     => 'New Form Submission: {form_title}',
		)
	);

	/*
	 * Section: Confirmation Defaults
	 */
	mc_register_settings_section(
		'forms',
		'confirmation',
		array(
			'title'       => 'Confirmation Defaults',
			'description' => 'Default confirmation settings for new forms.',
			'priority'    => 20,
		)
	);

	mc_register_setting_field(
		'forms',
		'confirmation',
		'default_confirmation_message',
		array(
			'type'        => 'html',
			'label'       => 'Default Success Message',
			'description' => 'Supports basic HTML (bold, italic, links). Used when a form has no per-form confirmation message set.',
			'default'     => 'Thank you! Your submission has been received.',
		)
	);

	mc_register_setting_field(
		'forms',
		'confirmation',
		'default_redirect_url',
		array(
			'type'        => 'url',
			'label'       => 'Default Redirect URL',
			'description' => 'Used when a per-form redirect URL has not been set.',
			'default'     => '',
		)
	);

	/*
	 * Section: Anti-Spam
	 */
	mc_register_settings_section(
		'forms',
		'anti_spam',
		array(
			'title'       => 'Anti-Spam',
			'description' => 'Configure spam protection for all forms.',
			'priority'    => 30,
		)
	);

	mc_register_setting_field(
		'forms',
		'anti_spam',
		'enable_honeypot',
		array(
			'type'    => 'checkbox',
			'label'   => 'Enable Honeypot Field',
			'default' => true,
		)
	);
}
