<?php

namespace App\Security\Detection;

/**
 * Weighted, categorised attack signatures.
 *
 * Weights are deliberately conservative: a single low-confidence hit never
 * blocks a request on its own. Correlation of several weak signals (or one
 * high-confidence payload) triggers enforcement.
 */
final class SignatureSet
{
    /** @var array<int, array{category:string, rule:string, pattern:string, weight:int}> */
    private array $rules = [];

    public function __construct()
    {
        $this->rules = array_merge(
            $this->sqlInjection(),
            $this->crossSiteScripting(),
            $this->pathTraversal(),
            $this->commandInjection(),
            $this->webshellAndUpload(),
            $this->templateAndDeserialisation(),
            $this->serverSideRequestForgery(),
            $this->scannerAndReconnaissance(),
        );

        $disabled = array_map('strtolower', (array) config('security.detection.disabled_categories', []));
        if ($disabled !== []) {
            $this->rules = array_values(array_filter(
                $this->rules,
                static fn (array $r): bool => ! in_array(strtolower($r['category']), $disabled, true)
            ));
        }
    }

    /** @return array<int, array{category:string, rule:string, pattern:string, weight:int}> */
    public function all(): array
    {
        return $this->rules;
    }

    public function count(): int
    {
        return count($this->rules);
    }

    private function sqlInjection(): array
    {
        return [
            // UNION SELECT in a request parameter is decisive: virtually no
            // legitimate e-commerce payload contains it, so it must be able
            // to block on its own rather than only after correlation.
            ['category' => 'sqli', 'rule' => 'union-select', 'weight' => 85, 'pattern' => '/\bunion\b\s*(?:all\s*)?\bselect\b/i'],
            ['category' => 'sqli', 'rule' => 'select-from', 'weight' => 55, 'pattern' => '/\bselect\b[\s\S]{0,120}?\bfrom\b[\s\S]{0,80}?\b(?:information_schema|users|mysql|pg_|sqlite_)/i'],
            ['category' => 'sqli', 'rule' => 'ddl-dml', 'weight' => 75, 'pattern' => '/\b(?:insert\s+into|update\s+\w+\s+set|delete\s+from|drop\s+(?:table|database|schema)|truncate\s+table|alter\s+table|create\s+table)\b/i'],
            ['category' => 'sqli', 'rule' => 'tautology', 'weight' => 65, 'pattern' => '/(?:[\'"`]\s*)?(?:\bor\b|\band\b)\s+(?:\d+\s*=\s*\d+|\'[^=]*\'\s*=\s*\'[^=]*\'|\w+\s+like\s+\w+)/i'],
            ['category' => 'sqli', 'rule' => 'sql-comment', 'weight' => 30, 'pattern' => '/(?:--\s|#\s*$|\/\*[\s\S]*?\*\/|;\s*(?:select|drop|update|delete|insert)\b)/i'],
            ['category' => 'sqli', 'rule' => 'time-based', 'weight' => 75, 'pattern' => '/\b(?:sleep|pg_sleep|benchmark|waitfor\s+delay|dbms_pipe\.receive_message)\s*\(/i'],
            ['category' => 'sqli', 'rule' => 'schema-probe', 'weight' => 60, 'pattern' => '/\b(?:information_schema|sysobjects|syscolumns|pg_catalog|sqlite_master|@@version|version\s*\(\s*\)|database\s*\(\s*\))/i'],
            ['category' => 'sqli', 'rule' => 'file-io', 'weight' => 70, 'pattern' => '/\b(?:load_file|into\s+(?:out|dump)file|xp_cmdshell|sp_oacreate|bulk\s+insert)\b/i'],
            ['category' => 'sqli', 'rule' => 'stacked-hex', 'weight' => 30, 'pattern' => '/\b0x[0-9a-f]{10,}\b|\b(?:char|chr|concat|concat_ws)\s*\(\s*\d/i'],
            ['category' => 'sqli', 'rule' => 'quote-breakout', 'weight' => 35, 'pattern' => '/[\'"]\s*(?:;|\)|\|\||&&)\s*(?:\w|--|\/\*|#)/'],
        ];
    }

    private function crossSiteScripting(): array
    {
        return [
            ['category' => 'xss', 'rule' => 'script-tag', 'weight' => 70, 'pattern' => '/<\s*script\b|<\s*\/\s*script\s*>/i'],
            ['category' => 'xss', 'rule' => 'event-handler', 'weight' => 65, 'pattern' => '/\bon(?:error|load|click|mouseover|mouseenter|focus|blur|submit|change|animationstart|toggle)\s*=/i'],
            ['category' => 'xss', 'rule' => 'js-protocol', 'weight' => 65, 'pattern' => '/\b(?:javascript|vbscript|data\s*:\s*text\/html|livescript)\s*:/i'],
            ['category' => 'xss', 'rule' => 'dangerous-tags', 'weight' => 55, 'pattern' => '/<\s*(?:iframe|svg|object|embed|applet|meta|base|form|link|style|math|frameset)\b/i'],
            ['category' => 'xss', 'rule' => 'dom-sink', 'weight' => 55, 'pattern' => '/\b(?:document\.cookie|document\.write|localStorage|sessionStorage|window\.location|eval\s*\(|Function\s*\(|setTimeout\s*\(\s*[\'"])/i'],
            ['category' => 'xss', 'rule' => 'obfuscated-payload', 'weight' => 45, 'pattern' => '/\b(?:String\.fromCharCode|atob\s*\(|unescape\s*\(|String\.raw\s*\()/i'],
            ['category' => 'xss', 'rule' => 'html-entity-breakout', 'weight' => 35, 'pattern' => '/&(?:#x?0?0?(?:60|62)|lt|gt);?\s*(?:script|img|svg|iframe)/i'],
            ['category' => 'xss', 'rule' => 'css-expression', 'weight' => 40, 'pattern' => '/\bexpression\s*\(|-moz-binding\s*:|behavior\s*:\s*url/i'],
        ];
    }

    private function pathTraversal(): array
    {
        return [
            ['category' => 'traversal', 'rule' => 'dot-dot-slash', 'weight' => 65, 'pattern' => '#(?:\.\./|\.\.\\\\|%2e%2e[/\\\\]|\\\\[.][.][/\\\\])#i'],
            ['category' => 'traversal', 'rule' => 'sensitive-files', 'weight' => 75, 'pattern' => '#\b(?:etc/passwd|etc/shadow|boot\.ini|win\.ini|proc/self/environ|proc/self/cmdline|\.env\b|\.htaccess|web\.config|wp-config\.php|\.git/(?:config|HEAD)|\.aws/credentials|id_rsa|authorized_keys)\b#i'],
            ['category' => 'traversal', 'rule' => 'php-wrappers', 'weight' => 75, 'pattern' => '#\b(?:php|file|zip|phar|glob|data|expect|ogg)://#i'],
            ['category' => 'traversal', 'rule' => 'windows-paths', 'weight' => 40, 'pattern' => '#[a-z]:\\\\(?:windows|inetpub|users|phpstudy|wwwroot)#i'],
            ['category' => 'traversal', 'rule' => 'null-byte', 'weight' => 45, 'pattern' => '#%00|\\x00#i'],
        ];
    }

    private function commandInjection(): array
    {
        return [
            ['category' => 'cmdi', 'rule' => 'shell-metacharacters', 'weight' => 45, 'pattern' => '/(?:;|\||&&|`|\$\()\s*(?:cat|ls|id|whoami|uname|curl|wget|nc|ncat|bash|sh|python|perl|ruby|php|powershell|cmd|certutil|base64)\b/i'],
            ['category' => 'cmdi', 'rule' => 'shell-path', 'weight' => 60, 'pattern' => '#/bin/(?:ba)?sh\b|/usr/bin/(?:python|perl|curl|wget)\b|cmd\.exe\s*/c|powershell(?:\.exe)?\s+-#i'],
            ['category' => 'cmdi', 'rule' => 'reverse-shell', 'weight' => 85, 'pattern' => '#\b(?:nc\s+-[a-z]*e|bash\s+-i\s*>&|mkfifo|/dev/tcp/|socket_create|fsockopen\s*\(\s*\$_)#i'],
            ['category' => 'cmdi', 'rule' => 'encoded-exec', 'weight' => 65, 'pattern' => '/\b(?:base64\s+-d|base64_decode|gzinflate|gzuncompress|str_rot13)\s*\(/i'],
        ];
    }

    private function webshellAndUpload(): array
    {
        return [
            ['category' => 'upload', 'rule' => 'executable-extension', 'weight' => 70, 'pattern' => '/\.(?:php[34578]?|phtml|phar|pht|asp|aspx|jsp|jspx|cgi|pl|py|sh|exe|dll|war|htaccess|user\.ini)(?:\.|$)/i'],
            ['category' => 'upload', 'rule' => 'double-extension', 'weight' => 55, 'pattern' => '/\.(?:jpg|jpeg|png|gif|pdf|xlsx?|docx?)\s*\.\s*(?:php[34578]?|phtml|phar|asp|jsp|exe|sh)/i'],
            ['category' => 'upload', 'rule' => 'webshell-body', 'weight' => 85, 'pattern' => '/(?:eval|assert|system|shell_exec|passthru|proc_open)\s*\(\s*(?:\$_(?:POST|GET|REQUEST|COOKIE)|base64_decode|gzinflate)/i'],
            ['category' => 'upload', 'rule' => 'webshell-marker', 'weight' => 80, 'pattern' => '/\b(?:FilesMan|WSO\s+shell|c99shell|r57shell|b374k|IndoXploit|eval\(\s*gzinflate)/i'],
        ];
    }

    private function templateAndDeserialisation(): array
    {
        return [
            ['category' => 'ssti', 'rule' => 'jndi-log4shell', 'weight' => 95, 'pattern' => '/\$\{\s*jndi\s*:\s*(?:ldap|rmi|dns|iiop|http)/i'],
            ['category' => 'ssti', 'rule' => 'template-expression', 'weight' => 50, 'pattern' => '/(?:\$\{[^}]{1,120}\}|\{\{[^}]{1,120}\}\}|#\{[^}]{1,120}\}|<%[\s\S]{0,120}%>)/'],
            ['category' => 'ssti', 'rule' => 'java-el', 'weight' => 70, 'pattern' => '/\b(?:java\.lang\.Runtime|ProcessBuilder|getRuntime\(\)|T\(java|freemarker\.template|ognl)/i'],
            ['category' => 'deserialisation', 'rule' => 'php-object', 'weight' => 65, 'pattern' => '/\bO:\d+:"[A-Za-z_\\\\][^"]{0,120}":\d+:\{|__wakeup|__destruct\b/'],
            ['category' => 'deserialisation', 'rule' => 'java-gadget', 'weight' => 60, 'pattern' => '/\b(?:rO0AB|ACED0005|org\.apache\.commons\.collections|ysoserial)\b/'],
        ];
    }

    private function serverSideRequestForgery(): array
    {
        return [
            ['category' => 'ssrf', 'rule' => 'internal-address', 'weight' => 55, 'pattern' => '#https?://(?:127\.|localhost|0\.0\.0\.0|169\.254\.|10\.\d+\.|192\.168\.|172\.(?:1[6-9]|2\d|3[01])\.|\[::1\])#i'],
            ['category' => 'ssrf', 'rule' => 'cloud-metadata', 'weight' => 85, 'pattern' => '/\b(?:169\.254\.169\.254|metadata\.google\.internal|metadata\.azure\.com|100\.100\.100\.200)\b/'],
            ['category' => 'ssrf', 'rule' => 'exotic-scheme', 'weight' => 60, 'pattern' => '#\b(?:gopher|dict|ldap|rmi|netdoc|jar)://#i'],
        ];
    }

    private function scannerAndReconnaissance(): array
    {
        return [
            ['category' => 'scanner', 'rule' => 'scanner-user-agent', 'weight' => 60, 'pattern' => '/\b(?:sqlmap|nikto|nmap|masscan|acunetix|nessus|wpscan|dirbuster|gobuster|hydra|metasploit|havij|arachni|zgrab|netsparker|openvas|fuzz faster)\b/i'],
            ['category' => 'scanner', 'rule' => 'scripted-client', 'weight' => 20, 'pattern' => '/\b(?:python-requests|go-http-client|libwww-perl|curl\/|wget\/|http_request2|aiohttp)/i'],
            ['category' => 'scanner', 'rule' => 'probe-path', 'weight' => 55, 'pattern' => '#/(?:\.env|\.git(?:/|$)|wp-admin|wp-login\.php|phpmyadmin|adminer|xmlrpc\.php|vendor/|storage/|\.aws/|actuator|debug|shell\.php|c99\.php|configuration\.php|telnet|solr|jenkins|console|\.DS_Store)#i'],
            ['category' => 'scanner', 'rule' => 'backup-artifact', 'weight' => 40, 'pattern' => '#\b(?:backup|dump|old|bak|sql|zip|tar\.gz)\.(?:sql|zip|tar|gz|bak)\b#i'],
            ['category' => 'scanner', 'rule' => 'crlf-injection', 'weight' => 45, 'pattern' => '/(?:%0d%0a|\r\n)\s*(?:set-cookie|location|content-type|host)\s*:/i'],
        ];
    }
}
