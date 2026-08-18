(() => {
  const API_ENDPOINT = '/api/track.php';
  const SESSION_ID = generateSessionId();
  const STAGE_MAP = {
    'index': 'landing',
    '2': 'cpf_analysis',
    '3': 'qualification_1',
    '4': 'qualification_2',
    '5': 'qualification_3',
    '6': 'qualification_4',
    '7': 'qualification_5',
    '8': 'qualification_6',
    '9': 'qualification_7',
    '10': 'qualification_8',
    '11': 'qualification_9',
    '12': 'qualification_10',
    '13-1': 'qualification_11',
    '13-2': 'qualification_12',
    '14-1': 'qualification_13',
    '14-2': 'qualification_14',
    'validacao': 'registration',
    'fatura': 'billing_date',
    'payment': 'payment_pix',
    'aprovado': 'approved'
  };

  const state = {
    sessionId: SESSION_ID,
    currentStage: detectStage(),
    stageStartTime: Date.now(),
    queue: [],
    isOnline: navigator.onLine
  };

  function generateSessionId() {
    const existing = sessionStorage.getItem('funnel_sid');
    if (existing) return existing;
    const sid = 'sid_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    sessionStorage.setItem('funnel_sid', sid);
    return sid;
  }

  function detectStage() {
    const filename = window.location.pathname.split('/').pop() || 'index';
    const base = filename.replace('.html', '');
    return STAGE_MAP[base] || base;
  }

  function track(eventName, data = {}) {
    if (typeof gtag !== 'undefined') {
      gtag('event', eventName, data);
    }

    const event = {
      timestamp: new Date().toISOString(),
      sessionId: state.sessionId,
      event: eventName,
      stage: state.currentStage,
      data: sanitizeData(data),
      device: getDeviceInfo()
    };

    state.queue.push(event);
    if (state.queue.length >= 2 || eventName === 'stage_conversion' || eventName === 'abandonment') {
      flushQueue();
    }
  }

  function sanitizeData(data) {
    const forbidden = ['nome', 'cpf', 'email', 'phone', 'endereco', 'cep', 'password', 'token'];
    const cleaned = {};
    for (const [key, value] of Object.entries(data)) {
      if (!forbidden.some(f => key.toLowerCase().includes(f))) {
        cleaned[key] = value;
      }
    }
    return cleaned;
  }

  function getDeviceInfo() {
    const ua = navigator.userAgent;
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(ua);
    const isChrome = /Chrome/.test(ua);
    const isFirefox = /Firefox/.test(ua);
    const isSafari = /Safari/.test(ua) && !/Chrome/.test(ua);

    return {
      mobile: isMobile,
      browser: isChrome ? 'chrome' : isFirefox ? 'firefox' : isSafari ? 'safari' : 'other',
      os: /Windows/.test(ua) ? 'windows' : /Mac/.test(ua) ? 'macos' : /Linux/.test(ua) ? 'linux' : /Android/.test(ua) ? 'android' : 'ios'
    };
  }

  function flushQueue() {
    if (state.queue.length === 0) return;

    const events = state.queue.splice(0, state.queue.length);
    const payload = JSON.stringify(events);

    if (navigator.sendBeacon) {
      navigator.sendBeacon(API_ENDPOINT, payload);
    } else {
      fetch(API_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: payload,
        keepalive: true
      }).catch(() => {});
    }
  }

  function handleStageChange(nextStage) {
    if (state.currentStage !== nextStage) {
      const timeOnStage = Math.round((Date.now() - state.stageStartTime) / 1000);
      track('stage_exit', {
        stage: state.currentStage,
        timeOnStage
      });

      state.currentStage = nextStage;
      state.stageStartTime = Date.now();
      track('stage_entry', { stage: nextStage });
    }
  }

  function trackConversion() {
    track('stage_conversion', {
      stage: state.currentStage,
      timeToConversion: Math.round((Date.now() - state.stageStartTime) / 1000)
    });
  }

  function trackError(errorName, context = {}) {
    track('form_error', {
      error: errorName,
      stage: state.currentStage,
      ...context
    });
  }

  function trackValidation(fieldName, isValid) {
    if (!isValid) {
      track('validation_error', {
        field: fieldName,
        stage: state.currentStage
      });
    }
  }

  window.addEventListener('beforeunload', () => {
    const timeOnStage = Math.round((Date.now() - state.stageStartTime) / 1000);
    track('page_unload', { timeOnStage });
    flushQueue();
  });

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      const timeOnStage = Math.round((Date.now() - state.stageStartTime) / 1000);
      track('abandonment', {
        stage: state.currentStage,
        timeOnStage,
        reason: 'page_hidden'
      });
      flushQueue();
    }
  });

  track('page_load', {
    stage: state.currentStage,
    referrer: document.referrer || 'direct'
  });

  window.Tracker = {
    track,
    handleStageChange,
    trackConversion,
    trackError,
    trackValidation,
    flushQueue,
    getSessionId: () => state.sessionId,
    getCurrentStage: () => state.currentStage
  };
})();
