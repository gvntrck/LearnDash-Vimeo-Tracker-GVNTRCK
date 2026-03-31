# LearnDash Vimeo Tracker

Plugin WordPress para rastreamento preciso de visualização de vídeos Vimeo em cursos LearnDash.

## Funcionalidades
- Rastreamento real de tempo assistido, considerando velocidade de reprodução e pulos.
- Conclusão automática de lições/tópicos no LearnDash ao atingir 70% do vídeo assistido.
- Relatório geral consolidado de progresso dos alunos.
- Dashboard detalhado de progresso por aluno em cada curso e lição.

## Instalação
1. Faça upload da pasta do plugin para `/wp-content/plugins/`.
2. Ative o plugin através do menu 'Plugins' no WordPress.
3. Certifique-se de que o plugin **LearnDash** está instalado e ativo.

## Como Usar
- O rastreamento é automático para qualquer vídeo do Vimeo incorporado nas lições do LearnDash.
- Quando o aluno atinge 70% do vídeo, o plugin tenta marcar automaticamente a etapa como concluída no LearnDash.
- Acesse **Vimeo Tracker → Relatório Geral** para visualizar as métricas de todos os alunos.
- Acesse **Vimeo Tracker → Progresso por Curso** para analisar o detalhamento de um aluno específico por curso.

## Alterar a Porcentagem de Conclusão
- O percentual padrão está definido em `70%`.
- Para alterar, edite o arquivo [learnDash-vimeo-tracker-gvntrck.php](/mnt/c/Users/Administrador/Documents/antigravity/vimeo-tracker/LearnDash-Vimeo-Tracker-GVNTRCK/learnDash-vimeo-tracker-gvntrck.php) na função `ldvt_get_completion_threshold()`.
- Procure esta linha:

```php
// Altere o valor 70 abaixo para definir a porcentagem mínima de conclusão automática no LearnDash.
$threshold = apply_filters( 'ldvt_completion_threshold', 70 );
```

- Exemplo: para exigir 80%, troque `70` por `80`.

## Requisitos
- WordPress 5.8+
- PHP 7.4+
- LearnDash
