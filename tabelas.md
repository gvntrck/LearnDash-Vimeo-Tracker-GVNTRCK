# Tabelas e dados usados pelo plugin

O plugin cria **1 tabela própria** no banco e também salva **1 opção** na tabela `wp_options` do WordPress.

## 1. Tabela própria do plugin

Nome real da tabela:
- ``{prefixo_wp}tempo_video``

Exemplo:
- se o prefixo do WordPress for `wp_`, a tabela será `wp_tempo_video`

Finalidade:
- guardar o progresso de visualização de vídeos Vimeo por usuário

Campos da tabela:

| Campo | Tipo | O que guarda |
| --- | --- | --- |
| `id` | `INT` | identificador interno do registro |
| `user_id` | `BIGINT UNSIGNED` | ID do usuário no WordPress |
| `video_id` | `VARCHAR(50)` | ID do vídeo no Vimeo |
| `tempo` | `INT` | maior tempo assistido pelo usuário nesse vídeo, em segundos |
| `curso_id` | `BIGINT` | ID do curso no LearnDash |
| `aula_id` | `BIGINT` | ID da aula ou tópico relacionado ao vídeo |
| `duracao_total` | `INT` | duração total do vídeo, em segundos |
| `data_registro` | `DATETIME` | data e hora da última atualização do progresso |

Índice importante:
- chave única em `user_id + video_id`
- isso faz o plugin manter **um registro por usuário por vídeo**

Comportamento ao salvar:
- se já existir um registro para o mesmo `user_id` e `video_id`, o plugin atualiza a linha existente
- o campo `tempo` fica com o maior valor já recebido (`GREATEST`)

## 2. Dados salvos em `wp_options`

O plugin não cria uma tabela separada para configurações. Ele usa a tabela padrão do WordPress:
- `wp_options` ou `{prefixo_wp}options`

Option name:
- `ldvt_settings`

Conteúdo salvo nessa opção:

| Chave | Tipo | O que guarda |
| --- | --- | --- |
| `auto_complete_enabled` | `int` | ativa ou desativa a conclusão automática da aula (`1` ou `0`) |
| `completion_threshold` | `float` | percentual mínimo para considerar a aula concluída |

Valores padrão:
- `auto_complete_enabled = 1`
- `completion_threshold = 70`

## 3. Tabelas que o plugin usa indiretamente

Além disso, o plugin consulta dados já existentes do WordPress e do LearnDash, mas **não cria essas tabelas**:
- usuários do WordPress, via `user_id`
- posts do LearnDash, como cursos, aulas e tópicos, via `curso_id` e `aula_id`
- `wp_options`, para ler/escrever `ldvt_settings`

## Resumo

O plugin tem, na prática:
- **1 tabela própria:** `{prefixo_wp}tempo_video`
- **1 registro em tabela padrão do WordPress:** `ldvt_settings` dentro de `{prefixo_wp}options`
