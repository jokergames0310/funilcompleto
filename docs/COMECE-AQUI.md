# Guia Rápido: Comece Aqui

Parabéns por ter um funil com ROI. Agora vamos turbinar com analytics e UX.

## Passo 1: Entender a Estrutura (5 min)

Novos arquivos criados:

```
js/
  └─ tracker.js              Rastreamento de eventos (coloque em TODAS páginas)

api/
  ├─ track.php              Recebe eventos do tracker
  ├─ webhook-pix.php        Recebe confirmação do Pix
  └─ analytics.php          Agrega dados pra dashboard

admin/
  └─ dashboard.html         Visualização do funil (abra no navegador)

data/logs/                   Criada automaticamente (não edite)

docs/
  ├─ INTEGRACAO.md          Guia passo-a-passo
  ├─ EXEMPLO-INTEGRACAO.html   Página com tudo integrado (copie e adapte)
  ├─ GOOGLE-ANALYTICS.md     Como integrar com Google Ads
  ├─ OTIMIZACOES-UX.md      Dicas de mobile, performance, etc
  └─ COMECE-AQUI.md         Este arquivo
```

## Passo 2: Adicionar o Tracker (10 min)

Em CADA página do funil (index.html, 2.html, 3.html, ... , aprovado.html), adicione na tag `<head>`:

```html
<script src="/js/tracker.js"></script>
```

Cole isso no `index.html`, pouco depois da tag `<meta charset>`:

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <script src="/js/tracker.js"></script>  <!-- <-- AQUI -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- resto do head... -->
```

Faça isso em TODAS as páginas.

## Passo 3: Integrar no Formulário de CPF (15 min)

Abra seu `2.html` (análise de CPF).

Procure o botão de submit. Antes de redirecionar pra próxima etapa, adicione:

```javascript
// Quando CPF é validado com sucesso:
Tracker.trackConversion();
window.location.href = '3.html';  // vai pra próxima página
```

Se quiser um exemplo completo, copie de `docs/EXEMPLO-INTEGRACAO.html` (é a página 2 com tudo pronto).

## Passo 4: Adicionar em Outras Conversões (10 min)

Faça o mesmo em:
- **Cada página de qualificação** (3, 4, 5, ... 14): ao avançar, `Tracker.trackConversion()`
- **Validação de dados**: se preencher tudo certo, `Tracker.trackConversion()`
- **Fatura (vencimento)**: ao escolher data, `Tracker.trackConversion()`
- **Pagamento**: quando Pix é gerado com sucesso, `Tracker.trackConversion()`

Padrão:
```javascript
function avancar() {
  // validar...
  if (tudoBem) {
    Tracker.trackConversion();
    irProxima();
  }
}
```

## Passo 5: Testar Sem Pagar (5 min)

1. Abra `http://localhost:8000` (se estiver rodando localmente) ou seu domínio
2. Preencha o funil até o final
3. Abra o DevTools (F12 > Network > XHR)
4. Veja requests pra `/api/track.php` sendo enviadas
5. Recargue: `http://seusite.com/admin/dashboard.html`
6. Deve mostrar seus eventos

Se não tiver acesso local, peça pro seu hospedeiro temporário subir o projeto:
- Hostinger: Deploy Web App (Git)
- Outro: FTP sobe os arquivos

## Passo 6: Integrar Pagamento Pix (após escolher gateway)

Quando você escolher seu gateway (SyncPay, Gerencianet, Stripe, etc), configure o webhook deles pra:

```
POST https://seu-site.com/api/webhook-pix.php
```

Com dados:
```json
{
  "sessionId": "sid_...",
  "status": "paid",
  "amount": 20.00,
  "currency": "BRL",
  "gateway": "syncpay",
  "paymentId": "pix_123"
}
```

Veja em `docs/INTEGRACAO.md` como pegar o sessionId.

## Passo 7: Google Ads (opcional, mas recomendado)

Se usa Google Ads, leia `docs/GOOGLE-ANALYTICS.md` pra:
- Integrar Google Tag Manager
- Rastrear conversões no Google
- Importar dados offline pro Google Ads

Isso te ajuda a calcular o ROI real da campanha.

## Passo 8: Otimizar UX (contínuo)

Abra `docs/OTIMIZACOES-UX.md` e aplique aos poucos:

- [ ] Testar em celular real
- [ ] Adicionar validação inline (feedback imediato)
- [ ] Lazy-load de imagens
- [ ] Remover animações pesadas
- [ ] Testar em rede 3G

## Passo 9: Ler Dashboard Diariamente (3 min/dia)

Abra `https://seu-site.com/admin/dashboard.html` todo dia.

Procure por:

1. **Qual etapa abandona mais?** (mais alto no funil = mais problema)
2. **Qual etapa tem mais erros?** (forma confusa, campo ruim)
3. **Qual dispositivo abandona mais?** (mobile tem problema?)
4. **Conversão total:** deve subir ou manter

## Perguntas Comuns

### P: Preciso mudar meu backend de pagamento?
R: Não. O tracker funciona com qualquer gateway. É só conectar o webhook depois.

### P: Perdi dados de visitantes antigos?
R: Sim. Rastreamento começa a partir de agora. Dados anteriores estão perdidos.

### P: Quanto custa?
R: Nada. Tudo roda no seu servidor (Hostinger). Logs são arquivos texto (NDJSON).

### P: Quanto espaço disco usa?
R: ~1MB por 1.000 eventos. R$20 ticket = ~10.000 eventos/mês = ~10MB/mês.

### P: Quem consegue acessar o dashboard?
R: Qualquer pessoa com a URL. Se quiser senha, peço pro próximo ciclo.

### P: E se o site cair?
R: Eventos são enviados quando possível. Se cair durante conversão, o evento pode ser perdido (risco baixo, já que é pico).

### P: Posso compartilhar com cliente/sócio?
R: Sim, mande a URL: `https://seu-site.com/admin/dashboard.html`

## Próximos Passos (após 1 semana)

1. Implementar uma senha no dashboard (segurança)
2. Adicionar relatórios por email (automático)
3. A/B test (testar 2 versões de botão, copy, etc)
4. Otimizar landing page com mais copy
5. Negociar comissão melhor com base em dados reais

## Suporte

Qualquer dúvida, revise:
- `docs/INTEGRACAO.md` se pergunta é sobre código
- `docs/OTIMIZACOES-UX.md` se pergunta é sobre design/mobile
- `docs/GOOGLE-ANALYTICS.md` se pergunta é sobre Google

Bora! Boa sorte. Seu ROI vai subir.
