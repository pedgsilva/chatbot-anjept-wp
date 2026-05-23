<?php
/**
 * Plugin Name: ChatBot ANJE
 * Description: Chatbot inteligente para www.anje.pt - órgãos sociais, programas, associados, contactos, estatutos
 * Version: 1.0.0
 * Author: Pedro Silva
 * Text Domain: chatbot-anje
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('CHATBOT_ANJE_VERSION', '1.0.0');
define('CHATBOT_ANJE_PATH', plugin_dir_path(__FILE__));
define('CHATBOT_ANJE_URL', plugin_dir_url(__FILE__));

require_once CHATBOT_ANJE_PATH . 'includes/class-chatbot-anje.php';

add_action('plugins_loaded', function() {
    ChatBot_ANJE::instance();
});

register_activation_hook(__FILE__, function() {
    $defaults = [
        'chatbot_name' => 'ChatBot ANJE',
        'openrouter_key' => '',
        'backend_url' => '',
        'model' => 'openrouter/owl-alpha',
        'welcome_message' => "Olá! 👋 Sou o assistente virtual da ANJE.\n\nPosso ajudar com:\n\n• 🏛️ Sobre a ANJE\n• 👥 Órgãos sociais\n• 📋 Programas (Incubação, Formação, Prémio)\n• 🤝 Como se tornar associado\n• 📞 Contactos\n• 📄 Estatutos\n\nO que procura?",
        'primary_color' => '#007bff',
        'position' => 'right',
        'max_tokens' => 600,
        'request_timeout' => 60,
        'show_on_all_pages' => 'yes',
        'temperature' => 0.3,
    ];
    if (!get_option('chatbot_anje_settings')) {
        add_option('chatbot_anje_settings', $defaults);
    }
});
