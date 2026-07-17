# 🔐 Segurança e Conformidade

* Controle de acesso baseado em capability (`format/smartcards:manageappearance`), exigida pelos
  dois web services de aparência (atividade e seção)
* Todo web service de aparência resolve o cmid/sectionid recebido, deriva o contexto real do
  curso, e chama `validate_context()` antes de qualquer checagem de capability — nunca opera
  sobre um id isolado sem amarrá-lo ao seu curso real
* Web services são consumidos via `core/ajax`, cujo transporte já inclui e valida a chave de
  sessão automaticamente
* Imagens de card enviadas nunca são armazenadas como recebidas: são decodificadas, recodificadas
  como PNG, e limitadas em tamanho/dimensão antes de serem gravadas no File API — excluindo
  payloads SVG/polyglot e descartando metadados embutidos por construção
* Nomes de arquivo são sempre fixos, nunca vindos da requisição — fechando qualquer superfície de
  path-traversal ao servir uma imagem de card
* Um valor de emoji único é validado no servidor (contagem de grafema + checagem de bloco de
  emoji), nunca confiado apenas ao seletor nativo do navegador
* Cor de fundo/título e fonte do título são validadas contra uma paleta curada no servidor —
  nunca aceitas como CSS livre
* Disponibilidade (badges de cadeado/prazo) é lida exclusivamente de `cm_info`/`section_info`,
  nunca recalculada — a mesma lógica de restrição que o resto do Moodle já aplica
* Uma seção restrita-mas-visível nunca vaza os cards ou o progresso de suas atividades — o mesmo
  portão que o próprio core aplica à lista de atividades
* Compatível com a External API do Moodle
* API de Privacidade totalmente implementada — declarado `null_provider`, já que o formato não
  armazena nenhum dado pessoal próprio (aparência customizada é configuração de curso/atividade,
  nunca ligada a um estudante)
