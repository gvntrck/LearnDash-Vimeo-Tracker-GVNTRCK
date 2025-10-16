# LearnDash Vimeo Tracker GVNTRCK

![Version](https://img.shields.io/badge/version-1.6.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.8+-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![LearnDash](https://img.shields.io/badge/LearnDash-Required-orange.svg)

Plugin WordPress para rastreamento preciso de visualização de vídeos Vimeo em cursos LearnDash.

## 🎯 Funcionalidades

### ✅ Rastreamento Inteligente de Vídeos
- **Progresso Real**: Rastreia intervalos assistidos, não apenas tempo corrido
- **Velocidade de Reprodução**: Considera diferentes velocidades (1x, 1.5x, 2x, etc.)
- **Mesclagem de Intervalos**: Evita contagem duplicada de trechos revistos
- **Detecção de Saltos**: Ignora pulos grandes no vídeo

### 📊 Relatório Geral
- Visualização de todos os registros de vídeos assistidos
- Filtro por email do aluno
- Paginação automática (50 registros por página)
- Exibição de:
  - Nome e email do aluno
  - Curso e aula
  - Tempo assistido vs duração total
  - Progresso percentual com barra visual
  - Data do último registro

### 📈 Relatório de Progresso por Curso
- **Dashboard completo** do progresso do aluno em um curso específico
- **Cards visuais** para cada lição do curso
- **Status por lição**:
  - 🟢 Completo (≥80%)
  - 🟡 Em Andamento (<80%)
  - ⚪ Não Iniciado
- **Resumo estatístico**:
  - Total de lições
  - Lições completas, em andamento e não iniciadas
  - Progresso médio das lições com vídeo
  - Taxa de conclusão do curso
  - Alertas contextuais de desempenho

## 📦 Instalação

1. Faça upload da pasta do plugin para `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Certifique-se de que o **LearnDash** está instalado e ativo
4. Acesse **Vimeo Tracker** no menu do admin

## 🚀 Como Usar

### Configuração Básica

1. **Incorpore vídeos Vimeo** nas suas lições do LearnDash
2. O plugin detecta automaticamente iframes do Vimeo
3. O rastreamento começa assim que o aluno assiste ao vídeo

### Visualizando Relatórios

#### Relatório Geral
1. Acesse **Vimeo Tracker → Relatório Geral**
2. Visualize todos os registros ou filtre por email
3. Analise o progresso geral dos alunos

#### Progresso por Curso
1. Acesse **Vimeo Tracker → Progresso por Curso**
2. Digite o **email do aluno**
3. Selecione o **curso** desejado
4. Clique em **Buscar**
5. Visualize:
   - Cards de cada lição com status e progresso
   - Resumo estatístico completo
   - Taxa de conclusão do curso

## 🔧 Requisitos

- **WordPress**: 5.8 ou superior
- **PHP**: 7.4 ou superior
- **LearnDash**: Plugin ativo e configurado
- **Vídeos Vimeo**: Incorporados nas lições

## 📊 Como Funciona o Rastreamento

### Sistema de Intervalos

O plugin rastreia **quais partes do vídeo foram assistidas**, não apenas o tempo total:

```javascript
// Exemplo:
// Vídeo de 100 segundos
// Assiste 0-30s em 2x → conta 30s de conteúdo
// Assiste 25-60s em 1x → mescla = 0-60s (60s únicos)
// Assiste 80-100s em 1.5x → conta 20s de conteúdo
// Total: 80 segundos de conteúdo visto = 80% do vídeo
```

### Proteções Implementadas

- ✅ Ignora retrocesso (só conta avanço)
- ✅ Ignora saltos grandes (seeks > 2s)
- ✅ Mescla intervalos sobrepostos automaticamente
- ✅ Não conta trechos revistos múltiplas vezes
- ✅ Considera velocidade de reprodução

### Salvamento Automático

O progresso é salvo automaticamente:
- A cada **3 minutos** durante a reprodução
- Quando o vídeo **termina**
- Quando o usuário **fecha a página**

## 🎨 Interface

### Tecnologias Utilizadas

- **Bootstrap 5.3.8**: Interface moderna e responsiva
- **Dashicons**: Ícones nativos do WordPress
- **Cards com Hover**: Efeitos visuais suaves
- **Barras de Progresso**: Cores dinâmicas baseadas no desempenho

### Cores dos Indicadores

- 🟢 **Verde** (≥80%): Excelente progresso
- 🟡 **Amarelo** (50-79%): Progresso moderado
- 🔴 **Vermelho** (<50%): Precisa melhorar
- ⚪ **Cinza**: Não iniciado

## 🗄️ Estrutura do Banco de Dados

### Tabela: `wp_tempo_video`

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | INT | ID único do registro |
| `user_id` | BIGINT | ID do usuário WordPress |
| `video_id` | VARCHAR(50) | ID do vídeo Vimeo |
| `tempo` | INT | Tempo assistido em segundos |
| `curso_id` | BIGINT | ID do curso LearnDash |
| `aula_id` | BIGINT | ID da lição LearnDash |
| `duracao_total` | INT | Duração total do vídeo |
| `data_registro` | DATETIME | Data/hora do último registro |

**Chave Única**: `(user_id, video_id)` - Evita registros duplicados

## 🔐 Segurança

- ✅ Sanitização de todos os inputs
- ✅ Prepared statements no banco de dados
- ✅ Verificação de permissões (`manage_options`)
- ✅ Nonce validation (via AJAX)
- ✅ Escape de outputs

## 📝 Changelog

Veja o arquivo [CHANGELOG.md](CHANGELOG.md) para histórico completo de versões.

## 🤝 Contribuindo

Contribuições são bem-vindas! Por favor:

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📄 Licença

Este plugin é licenciado sob a GPL v2 ou posterior.

## 👨‍💻 Autor

**GVNTRCK**
- GitHub: [@gvntrck](https://github.com/gvntrck)

## 🐛 Suporte

Encontrou um bug ou tem uma sugestão? 
[Abra uma issue](https://github.com/gvntrck/LearnDash-Vimeo-Tracker-GVNTRCK/issues)

---

**Desenvolvido com ❤️ para a comunidade LearnDash**
