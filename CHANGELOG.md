# Changelog - LearnDash Vimeo Tracker GVNTRCK

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
