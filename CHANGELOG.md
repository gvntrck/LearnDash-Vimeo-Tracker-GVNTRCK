# Changelog - LearnDash Vimeo Tracker GVNTRCK

## [1.7.1] - 2025-10-16

### 🐛 Correção de Layout

**Cards com Largura Total**

Corrigido problema onde os elementos `.card` ficavam estreitos no lado esquerdo da tela.

#### O que foi corrigido:

- Adicionado CSS para forçar cards a ocuparem 100% da largura disponível
- `.card { width: 100% !important; max-width: 100% !important; }`

#### Resultado:

- ✅ Cards agora ocupam toda a largura da tela
- ✅ Melhor aproveitamento do espaço
- ✅ Visualização mais limpa e profissional

---

## [1.7.0] - 2025-10-16

### 🎨 Mudança de Visualização

**Formato de Tabela no Relatório de Progresso por Curso**

A visualização do relatório "Progresso por Curso" foi alterada de **cards** para **tabela**, seguindo o mesmo padrão do "Relatório Geral".

#### O que mudou:

**Antes:**
- Visualização em cards (3 colunas)
- Informações distribuídas em cards individuais
- Hover effects e animações

**Agora:**
- Visualização em tabela responsiva
- Todas as aulas em uma única tabela
- Mesma estrutura do Relatório Geral
- Mais fácil de escanear e comparar dados

#### Colunas da Tabela:

1. **Aula** - Nome da aula
2. **Status** - Badge colorido (Completo/Em Andamento/Não Iniciado)
3. **Tempo Assistido** - Formato HH:MM:SS
4. **Duração Total** - Formato HH:MM:SS ou N/A
5. **Progresso** - Barra visual com percentual
6. **Última Visualização** - Data e hora ou "-"

#### Benefícios:

- ✅ Visualização mais compacta
- ✅ Facilita comparação entre aulas
- ✅ Consistência visual com Relatório Geral
- ✅ Melhor para cursos com muitas aulas
- ✅ Mais fácil de exportar/imprimir

---

## [1.6.3] - 2025-10-16

### 🔍 Melhorias de Diagnóstico

**Mensagens Informativas e Debug Aprimorado**

#### Novidades:

1. **Validação de Email Aprimorada**
   - Agora mostra mensagem clara quando o email não é encontrado
   - Indica se o usuário não está cadastrado no WordPress

2. **Detecção de Registros em Outros Cursos**
   - Se o aluno não tiver registros no curso selecionado, mas tiver em outros cursos, uma mensagem informativa é exibida
   - Mostra quantos registros existem em outros cursos
   - Sugere verificar o "Relatório Geral"

3. **Mensagens de Diagnóstico**
   - Indica possíveis causas quando não há registros:
     - Aluno assistiu vídeos em outro(s) curso(s)
     - `curso_id` não foi salvo corretamente
     - Vídeo assistido antes de associar a aula ao curso

#### Por que isso ajuda:

- ✅ Identifica rapidamente se o problema é de curso errado
- ✅ Ajuda a diagnosticar problemas de `curso_id` não salvo
- ✅ Orienta o usuário para onde encontrar os dados
- ✅ Evita confusão quando aluno aparece em um relatório mas não em outro

#### Resposta à Pergunta:

**Não há tempo mínimo para aparecer no relatório.** Se o aluno aparece no "Relatório Geral" mas não no "Progresso por Curso", as causas mais prováveis são:

1. O vídeo foi assistido em **outro curso** (não o selecionado)
2. O `curso_id` não foi salvo corretamente no banco de dados
3. O vídeo foi assistido antes da aula ser associada ao curso

---

## [1.6.2] - 2025-10-16

### 🔄 Ajuste de Nomenclatura

**Alteração de "Lições" para "Aulas"**

Todas as referências foram atualizadas para usar "Aulas" em vez de "Lições", alinhando com a terminologia preferida do usuário.

#### Mudanças:
- ✅ "Total de Lições" → "Total de Aulas"
- ✅ "Progresso Médio de Todas as Lições" → "Progresso Médio de Todas as Aulas"
- ✅ "Taxa de Conclusão (Lições ≥80%)" → "Taxa de Conclusão (Aulas ≥80%)"
- ✅ "X de Y lições completas" → "X de Y aulas completas"
- ✅ "Nenhum vídeo assistido nesta lição" → "Nenhum vídeo assistido nesta aula"
- ✅ Comentários no código atualizados

---

## [1.6.1] - 2025-10-16

### 🐛 Correção Importante

**Cálculo do Progresso Médio Corrigido**

#### O que foi corrigido:

**Antes (INCORRETO):**
- Calculava: `soma dos progressos ÷ lições com registro no banco`
- **Problema:** Ignorava lições não iniciadas
- **Exemplo:** 2 lições (100% + 80%) ÷ 2 = **90%** (inflado!)

**Agora (CORRETO):**
- Calcula: `soma dos progressos ÷ TODAS as lições do curso`
- **Solução:** Lições não iniciadas contam como 0%
- **Exemplo:** (100% + 80% + 0% + 0% + 0%) ÷ 5 = **36%** (real!)

#### Mudanças:

1. **Nome da Métrica Atualizado:**
   - Antes: "Progresso Médio das Lições com Vídeo"
   - Agora: "Progresso Médio de Todas as Lições"

2. **Texto Explicativo Adicionado:**
   - "Média considerando todas as X lições (inclusive não iniciadas)"

3. **Documentação Completa:**
   - Criado arquivo `METRICAS.md` explicando todos os cálculos

#### Por que isso importa:

- ✅ Reflete o progresso **real** do aluno no curso
- ✅ Não infla artificialmente os números
- ✅ Considera lições não iniciadas (0%)
- ✅ Não precisa saber duração de vídeos não assistidos

---

## [1.6.0] - 2025-10-16

### ✨ Nova Funcionalidade: Relatório de Progresso por Curso

**Dashboard Completo de Acompanhamento do Aluno**

Agora você pode visualizar o progresso detalhado de cada aluno em um curso específico!

#### Recursos:

1. **Filtros Inteligentes**
   - Busca por email do aluno
   - Seleção de curso LearnDash
   - Interface limpa e intuitiva

2. **Visualização por Cards**
   - Card individual para cada lição do curso
   - Status visual: Completo (≥80%), Em Andamento, Não Iniciado
   - Barra de progresso com cores dinâmicas
   - Tempo assistido vs duração total
   - Data da última visualização

3. **Resumo Geral Estatístico**
   - Total de lições no curso
   - Lições completas (≥80%)
   - Lições em andamento
   - Lições não iniciadas
   - Progresso médio das lições com vídeo
   - Taxa de conclusão do curso
   - Alertas contextuais de desempenho

4. **Indicadores Visuais**
   - 🟢 Verde: Completo (≥80%)
   - 🟡 Amarelo: Em Andamento (<80%)
   - ⚪ Cinza: Não Iniciado
   - Cards com hover effect
   - Ícones do Dashicons

#### Como Usar:

1. Acesse **Vimeo Tracker → Progresso por Curso**
2. Digite o email do aluno
3. Selecione o curso desejado
4. Clique em "Buscar"
5. Visualize o relatório completo!

#### Benefícios:

- ✅ Acompanhamento individual do aluno
- ✅ Identificação rápida de lições não assistidas
- ✅ Métricas de engajamento por curso
- ✅ Suporte à tomada de decisão pedagógica
- ✅ Interface responsiva e moderna

---

## [1.5.0] - 2025-10-16

### ✨ Novidades

**Rastreamento Real de Progresso com Velocidade de Reprodução**

Agora o plugin rastreia o **tempo real de conteúdo assistido**, independente da velocidade de reprodução!

#### Como Funciona:

1. **Rastreamento de Intervalos**
   - O sistema registra quais partes do vídeo foram assistidas (intervalos)
   - Exemplo: Se assistir dos 0-30s, depois dos 20-50s, registra 0-50s (50 segundos únicos)

2. **Velocidade de Reprodução**
   - Captura a velocidade atual (1x, 1.5x, 2x, etc.)
   - Monitora mudanças de velocidade durante a reprodução

3. **Cálculo Real**
   - Conta apenas os segundos únicos do vídeo que foram vistos
   - Se assistir 30s em 2x, conta 30s de conteúdo (não 15s)
   - Se assistir a mesma parte 2x, conta apenas 1x

4. **Mesclagem Inteligente**
   - Intervalos sobrepostos são automaticamente mesclados
   - Evita contagem duplicada de trechos revistos

#### Exemplo Prático:

**Cenário:**
- Vídeo de 100 segundos
- Assiste 0-30s em velocidade 2x (leva 15s reais)
- Assiste 25-60s em velocidade 1x (leva 35s reais)
- Assiste 80-100s em velocidade 1.5x (leva ~13s reais)

**Resultado:**
- **Tempo real assistido:** 65 segundos de conteúdo
- **Progresso:** 65% do vídeo
- **Tempo decorrido:** ~63 segundos de relógio

### 🔧 Melhorias Técnicas

- Sistema de intervalos com mesclagem automática
- Detecção de retrocesso (não conta tempo voltando)
- Validação de saltos grandes (ignora seeks maiores que 2s)
- Rastreamento de mudanças de velocidade via evento `playbackratechange`

### 📊 Benefícios

- ✅ Progresso preciso mesmo com velocidades variadas
- ✅ Não conta trechos pulados
- ✅ Não conta trechos revistos múltiplas vezes
- ✅ Detecta se o aluno realmente viu todo o conteúdo
- ✅ Compatível com todas as funcionalidades anteriores

---

## [1.4.1] - Versão Anterior

- Rastreamento básico de tempo assistido
- Integração com LearnDash
- Painel administrativo com filtros
