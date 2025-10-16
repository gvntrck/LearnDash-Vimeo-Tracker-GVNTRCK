# 📊 Entendendo as Métricas do Plugin

Este documento explica como cada métrica é calculada no relatório de **Progresso por Curso**.

---

## 🎯 Métricas Principais

### 1️⃣ **Total de Lições**
- **O que é:** Número total de lições cadastradas no curso LearnDash
- **Como é calculado:** `count( learndash_get_lesson_list( $curso_id ) )`
- **Exemplo:** Se o curso tem 20 lições = **20**

---

### 2️⃣ **Lições Completas (≥80%)**
- **O que é:** Lições onde o aluno assistiu ≥80% do vídeo
- **Critério:** `progresso >= 80%`
- **Exemplo:** 
  - Lição 1: 87% ✅ (conta)
  - Lição 2: 65% ❌ (não conta)
  - Lição 3: 0% ❌ (não conta)
  - **Total:** 1 lição completa

---

### 3️⃣ **Lições Em Andamento**
- **O que é:** Lições com vídeo iniciado mas <80%
- **Critério:** `0% < progresso < 80%`
- **Exemplo:**
  - Lição 1: 65% ✅ (conta)
  - Lição 2: 45% ✅ (conta)
  - Lição 3: 0% ❌ (não conta)
  - **Total:** 2 lições em andamento

---

### 4️⃣ **Lições Não Iniciadas**
- **O que é:** Lições onde o vídeo nunca foi assistido
- **Critério:** Não existe registro no banco de dados OU `progresso = 0%`
- **Exemplo:**
  - Lição sem registro no banco ✅ (conta)
  - Lição com 0% ✅ (conta)

---

## 📈 Indicadores de Desempenho

### 🔹 **Progresso Médio de Todas as Lições**

**Fórmula:**
```
Progresso Médio = (Soma de todos os progressos) ÷ (Total de lições)
```

**Como funciona:**
- Soma o progresso de **TODAS** as lições do curso
- Lições não iniciadas contam como **0%**
- Divide pelo total de lições

**Exemplo Prático:**

```
Curso com 5 lições:
- Lição 1: 100% (vídeo completo)
- Lição 2: 80% (vídeo quase completo)
- Lição 3: 50% (vídeo pela metade)
- Lição 4: 0% (não iniciada)
- Lição 5: 0% (não iniciada)

Cálculo:
(100 + 80 + 50 + 0 + 0) ÷ 5 = 230 ÷ 5 = 46%

Resultado: Progresso Médio = 46%
```

**Importante:**
- ✅ Considera lições não iniciadas (0%)
- ✅ Reflete o progresso real do curso inteiro
- ✅ Não precisa saber duração de vídeos não assistidos

---

### 🔹 **Taxa de Conclusão (Lições ≥80%)**

**Fórmula:**
```
Taxa de Conclusão = (Lições Completas) ÷ (Total de Lições) × 100
```

**Como funciona:**
- Conta quantas lições têm ≥80% de progresso
- Divide pelo total de lições do curso
- Multiplica por 100 para obter percentual

**Exemplo Prático:**

```
Curso com 20 lições:
- 11 lições com ≥80% (completas)
- 9 lições com <80% (incompletas ou não iniciadas)

Cálculo:
11 ÷ 20 × 100 = 55%

Resultado: Taxa de Conclusão = 55%
```

**Importante:**
- ✅ Baseado apenas em vídeos Vimeo
- ❌ **NÃO** é a conclusão da lição no LearnDash
- ✅ Mostra quantas lições o aluno "completou" assistindo os vídeos

---

## 🎨 Cores dos Indicadores

### Progresso Médio:
- 🟢 **Verde** (≥80%): Excelente progresso
- 🟡 **Amarelo** (50-79%): Progresso moderado
- 🔴 **Vermelho** (<50%): Precisa melhorar

### Taxa de Conclusão:
- 🟢 **Verde** (≥80%): Excelente conclusão
- 🔵 **Azul** (50-79%): Boa conclusão
- 🔴 **Vermelho** (<50%): Baixa conclusão

---

## ❓ Perguntas Frequentes

### **P: Por que o Progresso Médio é diferente da Taxa de Conclusão?**

**R:** São métricas diferentes:

- **Progresso Médio**: Média de quanto foi assistido em cada lição
  - Exemplo: 5 lições com [100%, 80%, 50%, 0%, 0%] = **46%**

- **Taxa de Conclusão**: Percentual de lições completas (≥80%)
  - Exemplo: 2 de 5 lições ≥80% = **40%**

---

### **P: Como o sistema sabe o progresso de lições não iniciadas?**

**R:** Lições não iniciadas são automaticamente consideradas **0%**:
- Se não há registro no banco = 0%
- Isso permite calcular o progresso médio real do curso

---

### **P: E se uma lição não tiver vídeo?**

**R:** Lições sem vídeo Vimeo:
- Aparecem como "Não Iniciado" no card
- Contam como 0% no progresso médio
- **Não** afetam a taxa de conclusão (não podem ser completas)

---

### **P: A Taxa de Conclusão considera o progresso do LearnDash?**

**R:** **NÃO**. A taxa de conclusão é baseada **apenas nos vídeos Vimeo**:
- ✅ Lição com vídeo ≥80% = Completa
- ❌ Progresso da lição no LearnDash = Não considerado

Para ver o progresso real do LearnDash, use os relatórios nativos do plugin.

---

## 📊 Exemplo Completo

```
Curso: WordPress Avançado (20 lições)

Status das Lições:
- 8 lições: 100% (completas)
- 3 lições: 90% (completas)
- 4 lições: 60% (em andamento)
- 3 lições: 30% (em andamento)
- 2 lições: 0% (não iniciadas)

Cálculos:

1. Total de Lições: 20

2. Lições Completas (≥80%):
   8 + 3 = 11 lições

3. Lições Em Andamento (<80%):
   4 + 3 = 7 lições

4. Lições Não Iniciadas:
   2 lições

5. Progresso Médio:
   (8×100 + 3×90 + 4×60 + 3×30 + 2×0) ÷ 20
   = (800 + 270 + 240 + 90 + 0) ÷ 20
   = 1400 ÷ 20
   = 70%

6. Taxa de Conclusão:
   11 ÷ 20 × 100 = 55%

Resultado:
✅ Progresso Médio: 70% (Amarelo - Bom)
✅ Taxa de Conclusão: 55% (Azul - Moderado)
```

---

## 🎯 Conclusão

As métricas fornecem uma visão completa do engajamento do aluno:

- **Progresso Médio**: Quanto do conteúdo foi consumido
- **Taxa de Conclusão**: Quantas lições foram "finalizadas"
- **Cards Individuais**: Detalhes de cada lição

Use essas informações para:
- ✅ Identificar alunos que precisam de suporte
- ✅ Avaliar o engajamento com o conteúdo
- ✅ Tomar decisões pedagógicas baseadas em dados
