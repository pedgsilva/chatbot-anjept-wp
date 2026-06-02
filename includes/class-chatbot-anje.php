<?php
/**
 * ChatBot ANJE - Classe Principal
 */

if (!defined('ABSPATH')) exit;

class ChatBot_ANJE {

    private static $instance = null;
    private $option_key = 'chatbot_anje_settings';

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_footer', [$this, 'render_chatbot'], 100);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_chatbot_anje_chat', [$this, 'handle_chat']);
        add_action('wp_ajax_nopriv_chatbot_anje_chat', [$this, 'handle_chat']);
    }

    private function get_settings() {
        $defaults = [
            'chatbot_name' => 'ChatBot ANJE',
            'openrouter_key' => '',
            'backend_url' => '',
            'model' => 'openrouter/owl-alpha',
            'welcome_message' => '',
            'primary_color' => '#007bff',
            'position' => 'right',
            'max_tokens' => 600,
            'request_timeout' => 90,
            'show_on_all_pages' => 'yes',
            'temperature' => 0.3,
        ];
        $s = get_option($this->option_key, []);
        return wp_parse_args($s, $defaults);
    }

    /* ================================================================
     * ASSETS
     * ================================================================ */

    public function enqueue_assets() {
        $s = $this->get_settings();
        if ($s['show_on_all_pages'] !== 'yes' && !is_front_page() && !is_page()) return;

        wp_register_style('chatbot-anje-css', false);
        wp_enqueue_style('chatbot-anje-css');
        wp_add_inline_style('chatbot-anje-css', $this->get_css($s));
    }

    private function get_css($s) {
        $c = esc_attr($s['primary_color']);
        $pos = esc_attr($s['position']);
        $side = ($pos === 'left') ? 'left:20px;' : 'right:20px;';
        $winSide = ($pos === 'left') ? 'left:0' : 'right:0';
        return "
        #chatbot-anje-widget{position:fixed;bottom:20px;{$side}z-index:999999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
        #chatbot-anje-toggle{padding:0;border:none;background:none;cursor:pointer;transition:transform .2s}
        #chatbot-anje-toggle:hover{transform:scale(1.08)}
        #chatbot-anje-window{position:absolute;bottom:105px;{$winSide};width:390px;height:560px;background:#fff;border-radius:16px;box-shadow:0 12px 48px rgba(0,0,0,.18);display:none;flex-direction:column;overflow:hidden;animation:cajFade .25s ease}
        @keyframes cajFade{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        #chatbot-anje-header{background:{$c};color:#fff;padding:14px 16px;display:flex;align-items:center;gap:10px}
        #chatbot-anje-header-text{flex:1}
        #chatbot-anje-header strong{display:block;font-size:14px;font-weight:600}
        #chatbot-anje-header small{font-size:11px;opacity:.85}
        #chatbot-anje-close{background:none;border:none;color:#fff;font-size:22px;cursor:pointer;opacity:.7;padding:4px;line-height:1}
        #chatbot-anje-close:hover{opacity:1}
        #chatbot-anje-messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:#f0f2f5}
        .caj-msg{max-width:85%;padding:10px 14px;border-radius:12px;font-size:13.5px;line-height:1.55;word-wrap:break-word;box-shadow:0 1px 2px rgba(0,0,0,.06)}
        .caj-bot{background:#fff;color:#222;align-self:flex-start;border-bottom-left-radius:4px}
        .caj-user{background:{$c};color:#fff;align-self:flex-end;border-bottom-right-radius:4px}
        .caj-bot a{color:#0055aa!important;text-decoration:underline!important;font-weight:500}
        .caj-bot a:hover{color:#003d7a!important;text-decoration:underline!important}
        .caj-bot strong{color:#1a1a2e}
        #chatbot-anje-typing{background:#fff;color:#888;align-self:flex-start;font-size:12px;font-style:italic;padding:6px 12px;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.06)}
        #chatbot-anje-input-area{display:flex;padding:10px 12px;background:#fff;border-top:1px solid #e8e8e8;gap:8px}
        #chatbot-anje-input{flex:1;padding:10px 14px;border:1px solid #ddd;border-radius:20px;outline:none;font-size:13.5px;font-family:inherit}
        #chatbot-anje-input:focus{border-color:{$c}}
        #chatbot-anje-send{width:42px;height:42px;border-radius:50%;border:none;background:{$c};color:#fff;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center}
        #chatbot-anje-send:disabled{background:#ccc;cursor:not-allowed}
        @media(max-width:480px){#chatbot-anje-window{width:calc(100vw - 16px);height:calc(100vh - 120px);right:0;left:0;margin:0 auto}}
        ";
    }

    /* ================================================================
     * RENDER CHATBOT
     * ================================================================ */

    public function render_chatbot() {
        $s = $this->get_settings();
        $name = esc_html($s['chatbot_name']);
        $welcome = html_entity_decode($s['welcome_message'] ?: "Olá! 👋 Sou o assistente virtual da ANJE.\n\nPosso ajudar com:\n• 🏛️ Sobre a ANJE\n• 👥 Órgãos sociais\n• 📋 Programas\n• 🤝 Como se tornar associado\n• 📞 Contactos\n\nO que procura?", ENT_QUOTES, 'UTF-8');
        $ajax = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('chatbot_anje_nonce');
        $timeout = intval($s['request_timeout']) * 1000;
        ?>
        <div id="chatbot-anje-widget">
<button id="chatbot-anje-toggle" aria-label="<?php echo $name; ?>" style="width:90px;height:90px;border-radius:50%;border:none;padding:0;overflow:hidden;background:none;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.2);"><img src="<?php echo plugin_dir_url(__FILE__); ?>../assets/BOT.jpg" alt="<?php echo $name; ?>" style="width:90px;height:90px;object-fit:contain;display:block;"></button>
            <div id="chatbot-anje-window">
                <div id="chatbot-anje-header">
                    <div id="chatbot-anje-header-text">
                        <strong><?php echo $name; ?></strong>
                        <small>Online</small>
                    </div>
                    <button id="chatbot-anje-close" aria-label="Fechar">&#10005;</button>
                </div>
                <div id="chatbot-anje-messages"></div>
                <div id="chatbot-anje-input-area">
                    <input type="text" id="chatbot-anje-input" placeholder="Escreva a sua pergunta..." maxlength="500">
                    <button id="chatbot-anje-send" aria-label="Enviar">&#10148;</button>
                </div>
            </div>
        </div>
        <script>
        (function(){
            var ajaxUrl=<?php echo json_encode($ajax);?>,nonce=<?php echo json_encode($nonce);?>;
            var welcome=<?php echo json_encode($welcome);?>;
            var busy=false,shown=false;
            var toggle=document.getElementById('chatbot-anje-toggle');
            var win=document.getElementById('chatbot-anje-window');
            var input=document.getElementById('chatbot-anje-input');
            var sendBtn=document.getElementById('chatbot-anje-send');
            var msgs=document.getElementById('chatbot-anje-messages');

            toggle.addEventListener('click',function(){
                if(win.style.display==='flex'){win.style.display='none';}
                else{win.style.display='flex';input.focus();if(!shown&&welcome){addMsg(welcome,'bot');shown=true;}}
            });
            document.getElementById('chatbot-anje-close').addEventListener('click',function(){win.style.display='none';});
            sendBtn.addEventListener('click',sendMsg);
            input.addEventListener('keypress',function(e){if(e.key==='Enter')sendMsg();});
            document.addEventListener('keydown',function(e){if(e.key==='Escape'&&win.style.display==='flex')win.style.display='none';});

            function sendMsg(){
                var msg=input.value.trim();
                if(!msg||busy)return;
                busy=true;sendBtn.disabled=true;
                addMsg(msg,'user');
                input.value='';
                addTyping();

                fetch(ajaxUrl,{
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:'action=chatbot_anje_chat&message='+encodeURIComponent(msg)+'&nonce='+nonce
                }).then(function(r){
                    removeTyping();
                    if(!r.body){
                        r.text().then(function(t){
                            try{var j=JSON.parse(t);addMsg(j.data&&j.data.response?j.data.response:'Erro.','bot');}
                            catch(e){addMsg(t||'Erro.','bot');}
                        });
                        return;
                    }
                    var reader=r.body.getReader();
                    var decoder=new TextDecoder();
                    var buf='';
                    var botDiv=addBotMsg('');
                    function read(){
                        reader.read().then(function(res){
                            if(res.done){done();return;}
                            buf+=decoder.decode(res.value,{stream:true});
                            var lines=buf.split('\n');
                            buf=lines.pop();
                            for(var i=0;i<lines.length;i++){
                                var line=lines[i].trim();
                                if(line.indexOf('data:')!==0)continue;
                                var data=line.substring(5).trim();
                                if(data==='[DONE]'){done();return;}
                                try{
                                    var obj=JSON.parse(data);
                                    if(obj.choices&&obj.choices[0]&&obj.choices[0].delta&&obj.choices[0].delta.content){
                                        botDiv.textContent+=obj.choices[0].delta.content;
                                        botDiv.innerHTML=renderMd(botDiv.textContent);
                                        botDiv.scrollIntoView({behavior:'smooth'});
                                    }
                                }catch(e){}
                            }
                            read();
                        }).catch(function(err){
                            addMsg('Erro: '+err.message,'bot');
                            done();
                        });
                    }
                    read();
                    function done(){busy=false;sendBtn.disabled=false;input.focus();}
                }).catch(function(err){
                    removeTyping();
                    addMsg('Erro de ligacao.','bot');
                    busy=false;sendBtn.disabled=false;input.focus();
                });
            }

            function addBotMsg(text){
                var d=document.createElement('div');
                d.className='caj-msg caj-bot';
                d.id='caj-bot-'+Date.now();
                d.textContent=text;
                msgs.appendChild(d);
                d.scrollIntoView({behavior:'smooth'});
                return d;
            }

            function addMsg(text,type){
                var d=document.createElement('div');
                d.className='caj-msg caj-'+type;
                d.innerHTML=renderMd(escapeHtml(text));
                msgs.appendChild(d);
                d.scrollIntoView({behavior:'smooth'});
            }

            function escapeHtml(t){var d=document.createElement('div');d.textContent=t;return d.innerHTML;}

            function renderMd(text){
                return text
                    .replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,'<a href="$2" target="_blank" rel="noopener" style="color:#0066ee!important;text-decoration:underline!important;font-weight:600!important">$1</a>')
                    .replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>')
                    .replace(/(https?:\/\/[^<>\s"'()]+)/g,'<a href="$1" target="_blank" rel="noopener" style="color:#0066ee!important;text-decoration:underline!important;font-weight:600!important">$1</a>')
                    .replace(/\n/g,'<br>');
            }

            function addTyping(){
                var d=document.createElement('div');d.id='chatbot-anje-typing';
                d.className='caj-msg';d.textContent='A escrever...';msgs.appendChild(d);
            }
            function removeTyping(){var t=document.getElementById('chatbot-anje-typing');if(t)t.remove();}
        })();
        </script>
        <?php
    }

    /* ================================================================
     * HANDLE CHAT
     * ================================================================ */

    public function handle_chat() {
        if (!check_ajax_referer('chatbot_anje_nonce', 'nonce', false)) {
            wp_send_json_error('Token invalido', 403);
        }
        $msg = sanitize_text_field($_POST['message'] ?? '');
        if (empty($msg)) wp_send_json_error('Vazio', 400);

        $s = $this->get_settings();
        $backend_url = esc_url_raw($s['backend_url']);
        $key = $s['openrouter_key'];

        if (empty($backend_url) && empty($key)) {
            wp_send_json_success(['response' => 'Configure a API Key em Definicoes > ChatBot ANJE']);
        }

        if (!empty($backend_url)) {
            $resp = $this->proxy_to_backend($backend_url, $msg, $s);
            wp_send_json_success(['response' => $resp]);
            return;
        }

        $this->call_openrouter_streaming($msg, $s);
    }

    private function proxy_to_backend($url, $msg, $s) {
        $r = wp_remote_post(trailingslashit($url) . 'chat', [
            'timeout' => intval($s['request_timeout']),
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['message' => $msg]),
        ]);
        if (is_wp_error($r)) return 'Erro: ' . $r->get_error_message();
        $d = json_decode(wp_remote_retrieve_body($r), true);
        return $d['response'] ?? 'Erro ao processar.';
    }

    private function call_openrouter_streaming($msg, $s) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $timeout = intval($s['request_timeout']);
        $model   = $s['model'] ?: 'openrouter/owl-alpha';
        $body = json_encode([
            'model'      => $model,
            'messages'   => [
                ['role' => 'system', 'content' => $this->get_system_prompt()],
                ['role' => 'user',   'content' => 'Pergunta: ' . $msg],
            ],
            'temperature' => floatval($s['temperature']),
            'max_tokens'  => intval($s['max_tokens']),
            'stream'      => true,
        ]);

        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$s['openrouter_key']}",
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_WRITEFUNCTION  => function($curl, $chunk) {
                $lines = explode("\n", $chunk);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, 'data:') !== 0) continue;
                    $data = trim(substr($line, 5));
                    if ($data === '[DONE]') {
                        echo "data: [DONE]\n\n";
                        flush();
                        return strlen($chunk);
                    }
                    echo "data: " . $data . "\n\n";
                    flush();
                }
                return strlen($chunk);
            },
        ]);

        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            echo "data: {\"error\":\"" . addslashes($err) . "\"}\n\n";
            flush();
        }

        echo "data: [DONE]\n\n";
        flush();
        wp_die();
    }

    /* ================================================================
     * SYSTEM PROMPT - Base de conhecimento completa da ANJE
     * ================================================================ */

    private function get_system_prompt() {
        return "Assistente virtual da ANJE (anje.pt) - Ass. Nacional de Jovens Empresarios.\nFundada 1986, utilidade publica. Sede: Casa do Farol, Rua Paula da Gama, 4169-006 Porto.\nEmail: anje@anje.pt | Tel: (+351) 220 108 000 | Fax: (+351) 220 108 010\n\nORG - Direcao: Pres. Carlos Carvalho, VPs Nuno Malheiro, Filipa Pinto de Carvalho, Goncalo Simoes de Almeida. Dir. Nac: Filipe Quinaz, Miguel Teixeira Bastos, Sofia Correia de Sousa, Tiago Araujo. Dir. N. Norte: Antonio Fragateiro. Dir. N. Centro: Beatriz Almeida. Dir. N. Alentejo: Tiago Abalroado. Dir. N. Algarve: Pedro Marcelino. Dir. N. Lisboa: Camilo Ferreira. Suplentes: Joao Pestana de Vasconcelos, Diogo Teixeira.\nMesa AG: Pres. Miguel Moreira da Silva, VP Ricardo Santos Lopes, Sec. Paula Melo, Suplentes Goncalo Sa, Diogo Pinheiro.\nConselho Fiscal: Pres. Catarina Azevedo, VP Pedro Cardoso, Vogais Sofia Xavier, Vitor Almeida, Goncalo Abreu. Suplentes Jose Miguel Oliveira, Manuela Borges.\nCentro Matosinhos: cematosinhos@anje.pt | Tel: +351 229 069 590\n\nPROGRAMAS:\n- Incubacao: 11 centros, modalidades PLAY(25E/mes), THINK(50E), START(100E), GROW(250E). Inclui: sede social, gestao de correio, atendimento telefonico, salas de reunioes. Centros: Matosinhos, Faro, Evora, Porto, Lisboa + 6 localidades. URL: https://anje.pt/linc/ e https://anje.pt/linc/incubacao-virtual/\n- Formacao: certificada, areas gestao/marketing/vendas/financas/juridico/digital. URL: https://anjeformacao.pt\n- Premio Jovem Empreendedor: distincao de projetos empresariais inovadores. Regulamento: https://anje.pt/premio-do-jovem-empreendedor/regulamento/ . Condicoes: https://anje.pt/premio-do-jovem-empreendedor/condicoes/\n- MOVE: programa de apoio ao empreendedorismo. URL: https://anje.pt/move/\n- Portugal Fashion: criado 1995 pela ANJE, promove a moda de autor nacional e internacionalmente, posiciona Portugal como fashion hub europeu e mundial, organiza fashion weeks e eventos internacionais (Paris, Milao, Londres, Nova Iorque, Madrid, etc.), mais de 200 eventos internacionais realizados. URL: https://portugalfashion.com/\n\nASSOCIAR (https://anje.pt/faz-te-socio/): 1. Aceder pagina Faz-te Socio, 2. Preencher Proposta de Adesao, 3. Consultar Condicoes em https://anje.pt/associados/, 4. Aguardar aprovacao da Direcao.\nQUOTAS: Individual 60E, Micro Empresa 100E, Pequena 200E, Media 400E, Grande 2500E. Aderente (ate 40 anos): quota em https://anje.pt/faz-te-socio/. Desconto 50% se adesao no ultimo trimestre. Pagamento por transferencia bancaria. Condicoes completas: https://anje.pt/condicoes-gerais-de-adesao-a-anje/\n\nCATEGORIAS: Aderente (ate 40a, potenciais empresarios/trabalhadores/universitarios), Efetivo (18-40a, socios/acionistas ou empresarios nome individual), Arcanje (41+a), Corporativo (coletivas: associacoes/fundacoes/sociedades/cooperativas), Honorario (singular/coletiva com contribuicao relevante para a ANJE).\n\nDIREITOS SOCIOS EFETIVOS: Aceder a todas as atividades e servicos da ANJE, participar na vida e gestao administrativa, usufruir de vantagens e direitos da Associacao, ser elegivel para cargos da ANJE (apos 6 meses), votar em eleicoes, convocar Assembleia Geral.\nDIREITOS ADERENTES: Mesmas regalias dos efetivos exceto direito de eleger/ser eleito. Podem participar em Assembleias Gerais sem voto.\nDIREITOS ARCANJES/CORPORATIVOS/HONORARIOS: Participar na atividade associativa mediante quota anual. Nao tem direito de eleger/ser eleitos nem participar em Assembleias Gerais.\n\nBENEFICIOS: Condicoes especiais na rede de parceiros (combustiveis, hotelaria, turismo, transportes). Acesso preferencial a financiamento, internacionalizacao e promocao. Apoio juridico e consultoria. Rede de contactos e representacao institucional. Descontos 10% em servicos ANJE (formacao, incubacao, consultoria). Acesso a incentivos financeiros e investidores.\n\nESTATUTOS: https://anje.pt/anje/estatutos/\n\nREGRAS: Portugues PT. **negrita** para titulos. URLs como texto simples (NUNCA markdown [t](url)).\nEstatutos->https://anje.pt/anje/estatutos/. Formacao->https://anjeformacao.pt. Espacos->https://anje.pt/espacos-anje/+reservasespacos@anje.pt. Loja Empreendedor->https://anje.pt/move/apoios/loja-do-empreendedor/+formulario em https://docs.google.com/forms/d/e/1FAIpQLSe6dxIMqUMgUVuWtPtYyiG8rlGzkGF9a8iaPNG6ZPvf4TZBUQ/viewform. Nao souber->anje@anje.pt";
    }

    /* ================================================================
     * ADMIN
     * ================================================================ */

    public function add_admin_menu() {
        add_options_page('ChatBot ANJE', 'ChatBot ANJE', 'manage_options', 'chatbot-anje', [$this, 'admin_page']);
    }

    public function register_settings() {
        register_setting('chatbot_anje_grp', $this->option_key, [$this, 'sanitize']);
    }

    public function sanitize($in) {
        $out = [];
        $out['chatbot_name'] = sanitize_text_field($in['chatbot_name'] ?? 'ChatBot ANJE');
        $out['openrouter_key'] = sanitize_text_field($in['openrouter_key'] ?? '');
        $out['backend_url'] = esc_url_raw($in['backend_url'] ?? '');
        $out['model'] = sanitize_text_field($in['model'] ?? 'openrouter/owl-alpha');
        $out['welcome_message'] = sanitize_textarea_field($in['welcome_message'] ?? '');
        $out['primary_color'] = sanitize_hex_color($in['primary_color'] ?? '#007bff');
        $out['position'] = in_array($in['position'] ?? '', ['left','right']) ? $in['position'] : 'right';
        $out['max_tokens'] = absint($in['max_tokens'] ?? 600);
        $out['request_timeout'] = absint($in['request_timeout'] ?? 90);
        $out['show_on_all_pages'] = ($in['show_on_all_pages'] ?? '') === 'yes' ? 'yes' : 'no';
        $out['temperature'] = floatval($in['temperature'] ?? 0.3);
        return $out;
    }

    public function admin_page() {
        $s = $this->get_settings();
        ?>
        <div class="wrap">
            <h1>🤖 ChatBot ANJE</h1>
            <form method="post" action="options.php">
                <?php settings_fields('chatbot_anje_grp'); ?>
                <table class="form-table">
                    <tr>
                        <th><label>Nome do ChatBot</label></th>
                        <td><input type="text" name="chatbot_anje_settings[chatbot_name]" value="<?php echo esc_attr($s['chatbot_name']); ?>" class="regular-text" placeholder="Ex: ChatBot ANJE, Assistente ANJE"></td>
                    </tr>
                    <tr>
                        <th><label>Mensagem de Boas-vindas</label></th>
                        <td><textarea name="chatbot_anje_settings[welcome_message]" rows="5" class="large-text"><?php echo esc_textarea($s['welcome_message']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label>OpenRouter API Key</label></th>
                        <td>
                            <input type="password" name="chatbot_anje_settings[openrouter_key]" value="<?php echo esc_attr($s['openrouter_key']); ?>" class="regular-text" placeholder="sk-or-...">
                            <p class="description">Obter em <a href="https://openrouter.ai/keys" target="_blank">openrouter.ai</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>URL do Backend (opcional)</label></th>
                        <td>
                            <input type="url" name="chatbot_anje_settings[backend_url]" value="<?php echo esc_attr($s['backend_url']); ?>" class="regular-text" placeholder="https://backend.exemplo.com">
                            <p class="description">Se preenchido, o chatbot usa este backend em vez da API direta.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Modelo LLM</label></th>
                        <td><input type="text" name="chatbot_anje_settings[model]" value="<?php echo esc_attr($s['model']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label>Temperatura</label></th>
                        <td><input type="number" name="chatbot_anje_settings[temperature]" value="<?php echo esc_attr($s['temperature']); ?>" min="0" max="1" step="0.1" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label>Max Tokens</label></th>
                        <td><input type="number" name="chatbot_anje_settings[max_tokens]" value="<?php echo esc_attr($s['max_tokens']); ?>" min="200" max="4000" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label>Timeout (segundos)</label></th>
                        <td><input type="number" name="chatbot_anje_settings[request_timeout]" value="<?php echo esc_attr($s['request_timeout']); ?>" min="15" max="120" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label>Cor Principal</label></th>
                        <td><input type="color" name="chatbot_anje_settings[primary_color]" value="<?php echo esc_attr($s['primary_color']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label>Posição</label></th>
                        <td>
                            <select name="chatbot_anje_settings[position]">
                                <option value="right" <?php selected($s['position'],'right'); ?>>Direita</option>
                                <option value="left" <?php selected($s['position'],'left'); ?>>Esquerda</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Mostrar em todas as páginas</th>
                        <td><label><input type="checkbox" name="chatbot_anje_settings[show_on_all_pages]" value="yes" <?php checked($s['show_on_all_pages'],'yes'); ?>> Sim</label></td>
                    </tr>
                </table>
                <?php submit_button('Guardar'); ?>
            </form>
            <hr>
            <h2>Estado</h2>
            <table class="widefat" style="max-width:500px">
                <tr><td>API Key</td><td><?php echo !empty($s['openrouter_key']) ? '<span style="color:green">✓ Configurada</span>' : '<span style="color:orange">✗ Não configurada</span>'; ?></td></tr>
                <tr><td>Backend URL</td><td><?php echo !empty($s['backend_url']) ? '<span style="color:green">✓ ' . esc_html($s['backend_url']) . '</span>' : '<span style="color:gray">Não configurado (usa API direta)</span>'; ?></td></tr>
                <tr><td>Nome</td><td><code><?php echo esc_html($s['chatbot_name']); ?></code></td></tr>
            </table>
        </div>
        <?php
    }
}
