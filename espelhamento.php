<?php
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

class PlgContentEspelhamento extends CMSPlugin
{
    public function onContentPrepare($context, &$article, &$params, $limitstart = 0)
    {
        $regex = '/{espelhamento\s+path="([^"]+)"}/i';

        if (preg_match_all($regex, $article->text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $basePath    = $match[1];
                $input       = Factory::getApplication()->input;
                $subPath     = $input->get('espelhamento_path', null, 'string');
                $currentPath = $subPath ?: $basePath;

                $output = $this->renderFileList($basePath, $currentPath);
                $article->text = str_replace($match[0], $output, $article->text);
            }
        }
    }

    /**
     * Detecta se é local ou remoto e delega.
     */
    private function renderFileList($basePath, $currentPath)
    {
        // tenta detectar esquema (sftp:// ftp://). Se não houver esquema, assume local.
        $parsed = @parse_url($currentPath);
        $scheme = $parsed['scheme'] ?? null;

        if ($scheme === 'sftp' || $scheme === 'ftp') {
            return $this->renderRemoteList($basePath, $currentPath, $scheme);
        }

        // fallback para local (comportamento original)
        return $this->renderLocalList($basePath, $currentPath);
    }

    /**
     * Listagem local (praticamente o seu código original).
     */
    private function renderLocalList($basePath, $currentPath)
    {
        if (!is_dir($currentPath)) {
            return "<p><em>Diretório não encontrado: {$currentPath}</em></p>";
        }

        $items = array_diff(scandir($currentPath), ['.', '..']);
        if (empty($items)) {
            return "<p><em>Sem arquivos disponíveis.</em></p>";
        }

        $folders = [];
        $files   = [];
        foreach ($items as $item) {
            $fullPath = rtrim($currentPath, '/') . '/' . $item;
            if (is_dir($fullPath)) {
                $folders[] = $item;
            } elseif (is_file($fullPath)) {
                $files[] = $item;
            }
        }

        // Ordenação inicial por nome descendente
        rsort($folders, SORT_NATURAL | SORT_FLAG_CASE);
        rsort($files,   SORT_NATURAL | SORT_FLAG_CASE);

        // ID único para o dropdown e container
        $uid = uniqid('espelh_');

        // Estilos modernos inline (mantive os seus)
        $html  = <<<CSS
<style>
#ordenacao-{$uid} {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.95rem;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg width='12' height='7' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23666' stroke-width='2' fill='none' fill-rule='evenodd'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
}
.espelhamento-ordenacao-container {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 0.5em;
    margin-bottom: 1em;
}
.espelhamento-ordenacao-container label {
    font-size: 0.95rem;
    color: #333;
}
</style>
CSS;

        // Dropdown modernizado
        $html .= '<div class="espelhamento-ordenacao-container">';
        $html .= '<label for="ordenacao-'.$uid.'">Ordenar por:</label>';
        $html .= '<select id="ordenacao-'.$uid.'">
                    <option value="name_asc">Nome A–Z</option>
                    <option value="name_desc" selected>Nome Z–A</option>
                    <option value="date_desc">Mais recentes</option>
                    <option value="date_asc">Mais antigos</option>
                  </select>';
        $html .= '</div>';

        // Container original de cards
        $html .= '<div class="espelhamento-container" id="'. $uid .'">';

        // Botão Voltar fixo
        if ($currentPath !== $basePath) {
            $parentPath = dirname($currentPath);
            $url = Uri::getInstance()->toString(['path'])
                 . '?espelhamento_path=' . urlencode($parentPath);
            $html .= '<a href="'. $url .'" class="espelhamento-card espelhamento-back"'
                   . ' data-name=".."'
                   . ' data-date="'. filemtime($parentPath) .'">'
                   . '🔙 Voltar'
                   . '</a>';
        }

        // Pastas
        foreach ($folders as $folder) {
            $folderPath = rtrim($currentPath, '/') . '/' . $folder;
            $url = Uri::getInstance()->toString(['path'])
                 . '?espelhamento_path=' . urlencode($folderPath);
            $html .= '<a href="'. $url .'" class="espelhamento-card"'
                   . ' data-name="'. htmlspecialchars($folder) .'"'
                   . ' data-date="'. filemtime($folderPath) .'">'
                   . '📁 '. htmlspecialchars($folder)
                   . '</a>';
        }

        // Arquivos (linkando para caminho público, como você fazia)
        foreach ($files as $file) {
            $fullPath = rtrim($currentPath, '/') . '/' . $file;
            $urlBase = str_replace('/var/www/html/intranet', '', $fullPath);
            $url = Uri::root() . ltrim($urlBase, '/');
            $html .= '<a href="'. $url .'" target="_blank" class="espelhamento-card"'
                   . ' data-name="'. htmlspecialchars($file) .'"'
                   . ' data-date="'. filemtime($fullPath) .'">'
                   . '📄 '. htmlspecialchars($file)
                   . '</a>';
        }

        $html .= '</div>'; // .espelhamento-container

        // Script para ordenação sem mover o back button
        $html .= <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('$uid');
    const select = document.getElementById('ordenacao-$uid');
    select.addEventListener('change', function() {
        const all = Array.from(container.children);
        const back = container.querySelector('.espelhamento-back');
        const items = all.filter(el => el !== back);

        const order = this.value;
        items.sort((a, b) => {
            const na = a.dataset.name.toLowerCase();
            const nb = b.dataset.name.toLowerCase();
            const da = +a.dataset.date;
            const db = +b.dataset.date;
            switch(order) {
                case 'name_asc':  return na.localeCompare(nb);
                case 'name_desc': return nb.localeCompare(na);
                case 'date_asc':  return da - db;
                case 'date_desc': return db - da;
            }
        });

        container.innerHTML = '';
        if (back) container.appendChild(back);
        items.forEach(i => container.appendChild(i));
    });
});
</script>
JS;

        return $html;
    }

    /**
     * Listagem remota (SFTP ou FTP). Mantém UI igual — folders navegáveis, arquivos mostrados (sem download).
     */
    private function renderRemoteList($basePath, $currentPath, $scheme)
    {
        // parse URL remoto: sftp://user:pass@host:port/path
        $parsed = parse_url($currentPath);
        if (!$parsed) {
            return "<p><em>URL inválida: {$currentPath}</em></p>";
        }

        $user = $parsed['user'] ?? null;
        $pass = $parsed['pass'] ?? null;
        $host = $parsed['host'] ?? null;
        $port = $parsed['port'] ?? null;
        $path = $parsed['path'] ?? '/';

        $folders = [];
        $files   = [];

        if ($scheme === 'sftp') {
            if (!function_exists('ssh2_connect')) {
                return "<p><em>Extensão SSH2 não disponível no servidor. Instale php-ssh2 para SFTP.</em></p>";
            }
            $port = $port ?: 22;
            $conn = @ssh2_connect($host, $port);
            if (!$conn) return "<p><em>Não foi possível conectar SFTP a {$host}:{$port}</em></p>";

            // autenticação por senha (se fornecida) — caso contrário espera chave/agent
            if ($user && $pass) {
                if (!@ssh2_auth_password($conn, $user, $pass)) {
                    return "<p><em>Falha na autenticação SFTP para {$user}@{$host}</em></p>";
                }
            }
            $sftp = @ssh2_sftp($conn);
            if (!$sftp) return "<p><em>Erro ao iniciar SFTP.</em></p>";

            $remoteRoot = "ssh2.sftp://". intval($sftp) . $path;
            $dh = @opendir($remoteRoot);
            if (!$dh) return "<p><em>Diretório remoto não acessível: {$path}</em></p>";

            while (false !== ($entry = readdir($dh))) {
                if ($entry === '.' || $entry === '..') continue;
                $fullPath = rtrim($path, '/') . '/' . $entry;
                $fullWrapped = "ssh2.sftp://". intval($sftp) . $fullPath;
                if (@is_dir($fullWrapped)) {
                    $folders[] = ['name'=>$entry, 'date'=>@filemtime($fullWrapped), 'path'=>$fullPath];
                } else {
                    $files[] = ['name'=>$entry, 'date'=>@filemtime($fullWrapped), 'path'=>$fullPath];
                }
            }
            closedir($dh);
        } elseif ($scheme === 'ftp') {
            if (!function_exists('ftp_connect')) {
                return "<p><em>Suporte FTP não disponível no PHP.</em></p>";
            }
            $port = $port ?: 21;
            $conn = @ftp_connect($host, $port, 10);
            if (!$conn) return "<p><em>Não foi possível conectar FTP a {$host}:{$port}</em></p>";
            $login = @ftp_login($conn, $user ?: 'anonymous', $pass ?: '');
            if (!$login) {
                ftp_close($conn);
                return "<p><em>Falha na autenticação FTP para {$user}@{$host}</em></p>";
            }
            ftp_pasv($conn, true);

            $raw = @ftp_nlist($conn, $path);
            if ($raw === false) {
                ftp_close($conn);
                return "<p><em>Diretório remoto não acessível: {$path}</em></p>";
            }

            foreach ($raw as $entry) {
                $basename = basename($entry);
                if ($basename === '.' || $basename === '..') continue;
                // tenta usar ftp_size para distinguir arquivo/pasta (size = -1 => diretório)
                $size = @ftp_size($conn, $entry);
                $mtime = @ftp_mdtm($conn, $entry);
                if ($size === -1) {
                    $folders[] = ['name'=>$basename, 'date'=>($mtime === -1 ? time() : $mtime), 'path'=>$entry];
                } else {
                    $files[] = ['name'=>$basename, 'date'=>($mtime === -1 ? time() : $mtime), 'path'=>$entry];
                }
            }
            ftp_close($conn);
        }

        // Ordenações iniciais (mantive o comportamento: name desc)
        usort($folders, function($a,$b){ return strcasecmp($b['name'],$a['name']); });
        usort($files, function($a,$b){ return strcasecmp($b['name'],$a['name']); });

        $uid = uniqid('espelh_');

        // Reuso do CSS/HTML o mais próximo possível do seu original
        $html  = <<<CSS
<style>
#ordenacao-{$uid} {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.95rem;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg width='12' height='7' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23666' stroke-width='2' fill='none' fill-rule='evenodd'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
}
.espelhamento-ordenacao-container {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 0.5em;
    margin-bottom: 1em;
}
.espelhamento-ordenacao-container label {
    font-size: 0.95rem;
    color: #333;
}
</style>
CSS;

        $html .= '<div class="espelhamento-ordenacao-container">';
        $html .= '<label for="ordenacao-'.$uid.'">Ordenar por:</label>';
        $html .= '<select id="ordenacao-'.$uid.'">
                    <option value="name_asc">Nome A–Z</option>
                    <option value="name_desc" selected>Nome Z–A</option>
                    <option value="date_desc">Mais recentes</option>
                    <option value="date_asc">Mais antigos</option>
                  </select>';
        $html .= '</div>';

        $html .= '<div class="espelhamento-container" id="'. $uid .'">';

        // Botão Voltar: reconstruí a URL remota do parent
        $parentPath = dirname($path);
        if ($parentPath !== $path) {
            $parentUrl = $this->rebuildRemoteUrl($scheme, $user, $pass, $host, $port, $parentPath);
            $url = Uri::getInstance()->toString(['path']) . '?espelhamento_path=' . urlencode($parentUrl);
            $html .= '<a href="'. $url .'" class="espelhamento-card espelhamento-back"'
                   . ' data-name=".."'
                   . ' data-date="'. time() .'">'
                   . '🔙 Voltar'
                   . '</a>';
        }

        // Pastas (navegáveis)
        foreach ($folders as $folder) {
            $folderUrl = $this->rebuildRemoteUrl($scheme, $user, $pass, $host, $port, $folder['path']);
            $url = Uri::getInstance()->toString(['path']) . '?espelhamento_path=' . urlencode($folderUrl);
            $html .= '<a href="'. $url .'" class="espelhamento-card"'
                   . ' data-name="'. htmlspecialchars($folder['name']) .'"'
                   . ' data-date="'. intval($folder['date']) .'">'
                   . '📁 '. htmlspecialchars($folder['name'])
                   . '</a>';
        }

        // Arquivos -> mostrados, sem provisionar download via plugin
        foreach ($files as $file) {
            // Exibimos como texto (se quiser link direto, pode-se usar ftp://... ou sftp://...; aqui preferi não linkar)
            $html .= '<span class="espelhamento-card"'
                   . ' data-name="'. htmlspecialchars($file['name']) .'"'
                   . ' data-date="'. intval($file['date']) .'">'
                   . '📄 '. htmlspecialchars($file['name'])
                   . '</span>';
        }

        $html .= '</div>'; // container

        // Script de ordenação idêntico ao seu original
        $html .= <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('$uid');
    const select = document.getElementById('ordenacao-$uid');
    select.addEventListener('change', function() {
        const all = Array.from(container.children);
        const back = container.querySelector('.espelhamento-back');
        const items = all.filter(el => el !== back);

        const order = this.value;
        items.sort((a, b) => {
            const na = a.dataset.name.toLowerCase();
            const nb = b.dataset.name.toLowerCase();
            const da = +a.dataset.date;
            const db = +b.dataset.date;
            switch(order) {
                case 'name_asc':  return na.localeCompare(nb);
                case 'name_desc': return nb.localeCompare(na);
                case 'date_asc':  return da - db;
                case 'date_desc': return db - da;
            }
        });

        container.innerHTML = '';
        if (back) container.appendChild(back);
        items.forEach(i => container.appendChild(i));
    });
});
</script>
JS;

        return $html;
    }

    /**
     * Reconstrói uma URL remota (mantendo usuário/porta se houver).
     */
    private function rebuildRemoteUrl($scheme, $user, $pass, $host, $port, $path)
    {
        // NOTE: mantemos user e pass caso existam (se você não quiser expor pass em URL, remova aqui)
        $auth = '';
        if ($user !== null && $user !== '') {
            $auth = rawurlencode($user);
            if ($pass !== null && $pass !== '') $auth .= ':' . rawurlencode($pass);
            $auth .= '@';
        }
        $portPart = $port ? ':' . intval($port) : '';
        return "{$scheme}://{$auth}{$host}{$portPart}{$path}";
    }
}
