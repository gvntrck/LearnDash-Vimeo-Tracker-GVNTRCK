# LearnDash Vimeo Tracker

Plugin WordPress para rastreamento preciso de visualização de vídeos Vimeo em cursos LearnDash.

## Funcionalidades
- Rastreamento real de tempo assistido, considerando velocidade de reprodução e pulos.
- Conclusão automática de lições/tópicos no LearnDash, com controle por site para ativar/desativar o recurso.
- Relatório geral consolidado de progresso dos alunos.
- Dashboard detalhado de progresso por aluno em cada curso e lição.
- Aba **Ajustes** no admin do plugin para configurar a conclusão automática e a porcentagem mínima.

## Instalação
1. Faça upload da pasta do plugin para `/wp-content/plugins/`.
2. Ative o plugin através do menu 'Plugins' no WordPress.
3. Certifique-se de que o plugin **LearnDash** está instalado e ativo.

## Como Usar
- O rastreamento é automático para qualquer vídeo do Vimeo incorporado nas lições do LearnDash.
- Acesse **Vimeo Tracker → Relatório Geral** para visualizar as métricas de todos os alunos.
- Acesse **Vimeo Tracker → Progresso por Curso** para analisar o detalhamento de um aluno específico por curso.
- Acesse **Vimeo Tracker → Ajustes** para:
  - ligar ou desligar a conclusão automática no LearnDash
  - definir a porcentagem mínima de conclusão para cada site

## Alterar a Porcentagem de Conclusão
- O percentual é configurado pela aba **Vimeo Tracker → Ajustes**.
- O valor padrão é `70%`, mas pode ser alterado por site diretamente no admin.
- Se a conclusão automática estiver desligada, o plugin continua rastreando o vídeo normalmente, sem marcar a aula como concluída no LearnDash.

## Requisitos
- WordPress 5.8+
- PHP 7.4+
- LearnDash
