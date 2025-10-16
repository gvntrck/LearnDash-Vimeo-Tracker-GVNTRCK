# Changelog - LearnDash Vimeo Tracker GVNTRCK

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
