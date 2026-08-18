/**
 * Tracker offline para Google Ads
 * Captura gclid/gbraid/wbraid e envia eventos ao backend
 */

const Tracker = {
  // Configuracao
  config: {
    apiEndpoint: '/api/track.php',
    offlineEndpoint: '/api/offline-conversion.php',
    batchInterval: 2000,
    batchSize: 10,
    storageKey: 'tracker_queue'
  },

  // Identifiers
  ids: {
    sessionId: null,
    gclid: null,
    gbraid: null,
    wbraid: null,
    orderId: null,
    amount: null
  },

  // Fila de eventos
  queue: [],
  batchTimer: null,

  // Inicializa o tracker
  init() {
    this.ids.sessionId = this.getOrCreateSessionId();
    this.captureGoogleIds();
    this.setupBeaconHandler();
    console.log('✓ Tracker inicializado', {
      sessionId: this.ids.sessionId,
      gclid: this.ids.gclid,
      gbraid: this.ids.gbraid
    });
  },

  // Gera ou recupera sessionId
  getOrCreateSessionId() {
    let sid = sessionStorage.getItem('tracker_sid');
    if (!sid) {
      sid = this.generateUUID();
      sessionStorage.setItem('tracker_sid', sid);
    }
    return sid;
  },

  // Captura gclid/gbraid/wbraid da URL
  captureGoogleIds() {
    const url = new URL(window.location.href);
    this.ids.gclid = url.searchParams.get('gclid');
    this.ids.gbraid = url.searchParams.get('gbraid');
    this.ids.wbraid = url.searchParams.get('wbraid');

    if (this.ids.gclid || this.ids.gbraid || this.ids.wbraid) {
      sessionStorage.setItem('tracker_google_ids', JSON.stringify({
        gclid: this.ids.gclid,
        gbraid: this.ids.gbraid,
        wbraid: this.ids.wbraid
      }));
    }
  },

  // Registra um evento
  event(eventName, data = {}) {
    const event = {
      timestamp: new Date().toISOString(),
      sessionId: this.ids.sessionId,
      event: eventName,
      stage: data.stage || null,
      data: data,
      device: this.getDeviceInfo()
    };

    this.queue.push(event);
    console.log(`📍 Evento: ${eventName}`, data);

    if (this.queue.length >= this.config.batchSize) {
      this.flush();
    } else if (!this.batchTimer) {
      this.batchTimer = setTimeout(() => this.flush(), this.config.batchInterval);
    }
  },

  // Registra um pedido com gclid
  registerOrder(orderId, amount, paymentMethod) {
    this.ids.orderId = orderId;
    this.ids.amount = amount;

    const orderData = {
      orderId,
      amount,
      paymentMethod,
      gclid: this.ids.gclid,
      gbraid: this.ids.gbraid,
      wbraid: this.ids.wbraid
    };

    sessionStorage.setItem('tracker_order', JSON.stringify(orderData));
    this.event('order_created', {
      stage: 'payment_pix',
      orderId,
      amount,
      paymentMethod
    });
  },

  // Envia fila de eventos pro backend
  async flush() {
    if (this.queue.length === 0) return;

    clearTimeout(this.batchTimer);
    this.batchTimer = null;

    const events = [...this.queue];
    this.queue = [];

    try {
      await fetch(this.config.apiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        keepalive: true,
        body: JSON.stringify(events)
      });
    } catch (err) {
      console.error('❌ Erro ao enviar eventos:', err);
      // Recoloca na fila se falhar
      this.queue.unshift(...events);
    }
  },

  // Registra conversao offline (chamado pelo webhook de pagamento)
  async recordOfflineConversion(orderId, amount, paymentTime) {
    try {
      const response = await fetch(this.config.offlineEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        keepalive: true,
        body: JSON.stringify({
          orderId,
          amount,
          paymentTime,
          sessionId: this.ids.sessionId,
          gclid: this.ids.gclid,
          gbraid: this.ids.gbraid,
          wbraid: this.ids.wbraid
        })
      });

      if (response.ok) {
        console.log('✓ Conversao offline registrada:', orderId);
      }
    } catch (err) {
      console.error('❌ Erro ao registrar conversao:', err);
    }
  },

  // Handler pra enviar fila ao sair da pagina
  setupBeaconHandler() {
    document.addEventListener('pagehide', () => {
      if (this.queue.length > 0) {
        navigator.sendBeacon(
          this.config.apiEndpoint,
          JSON.stringify(this.queue)
        );
      }
    });

    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'hidden' && this.queue.length > 0) {
        this.flush();
      }
    });
  },

  // Recupera Google IDs armazenados
  getStoredGoogleIds() {
    const stored = sessionStorage.getItem('tracker_google_ids');
    return stored ? JSON.parse(stored) : {};
  },

  // Recupera dados do pedido armazenado
  getStoredOrder() {
    const stored = sessionStorage.getItem('tracker_order');
    return stored ? JSON.parse(stored) : null;
  },

  // Utilitarios
  generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  },

  getDeviceInfo() {
    return {
      userAgent: navigator.userAgent,
      language: navigator.language,
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      timestamp: new Date().toISOString()
    };
  }
};

// Inicializa ao carregar
document.addEventListener('DOMContentLoaded', () => {
  Tracker.init();
});

// Fallback se DOM ja carregou
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => Tracker.init());
} else {
  Tracker.init();
}
