(function () {
  "use strict";

  const COLLECT_ENDPOINT = "https://collector.bananabread.bar/collect.php";

  // ---------- session identity (no cookies) ----------
  function getSessionId() {
    let sid = sessionStorage.getItem("_collector_sid");
    if (!sid) {
      sid = Math.random().toString(36).slice(2) + Date.now().toString(36);
      sessionStorage.setItem("_collector_sid", sid);
    }
    return sid;
  }

  const session_id = getSessionId();
  const page = window.location.href;

  // ---------- event queue + flush ----------
  const queue = [];
  let flushTimer = null;

  function enqueue(eventType, data) {
    queue.push({
      eventType,
      page,
      session_id,
      ts: new Date().toISOString(),
      data: data || {}
    });

    if (!flushTimer) {
      flushTimer = setTimeout(flush, 1000);
    }
  }

  function flush() {
    flushTimer = null;
    if (queue.length === 0) return;

    const payload = {
      page,
      session_id,
      batchTs: new Date().toISOString(),
      events: queue.splice(0, queue.length)
    };

    const bodyString = JSON.stringify(payload);

    // Preferred: sendBeacon (survives unload). Use STRING so it sends as text/plain (often no preflight).
    let sent = false;
    if (navigator.sendBeacon) {
      try {
        sent = navigator.sendBeacon(COLLECT_ENDPOINT, bodyString);
      } catch (e) {
        sent = false;
      }
    }

    // Fallback: fetch (keepalive for unload scenarios)
    if (!sent) {
      fetch(COLLECT_ENDPOINT, {
        method: "POST",
        mode: "cors",
        headers: { "Content-Type": "application/json" },
        body: bodyString,
        keepalive: true
      }).catch(() => {});
    }
  }

  // ---------- “manual” capability checks (images/css) ----------
  function checkImagesEnabled(cb) {
    const img = new Image();
    img.onload = () => cb(true);
    img.onerror = () => cb(false);
    img.src = "https://collector.bananabread.bar/pixel.png?ts=" + Date.now();
  }

  function checkCssEnabled() {
    const el = document.createElement("div");
    el.id = "_css_test";
    document.body.appendChild(el);
    const left = window.getComputedStyle(el).left;
    el.remove();
    return left === "-9999px";
  }

  // ---------- STATIC data (after page load) ----------
  function collectStatic() {
    const staticData = {
      user_agent: navigator.userAgent,
      language: navigator.language || null,
      accepts_cookies: !!navigator.cookieEnabled,
      javascript_enabled: true,
      screen: { width: window.screen.width, height: window.screen.height },
      window: { width: window.innerWidth, height: window.innerHeight },
      network:
        ("connection" in navigator && navigator.connection)
          ? {
              effectiveType: navigator.connection.effectiveType || null,
              downlink: navigator.connection.downlink || null,
              rtt: navigator.connection.rtt || null,
              saveData: navigator.connection.saveData || null
            }
          : null,
      css_enabled: checkCssEnabled(),
      images_enabled: null
    };

    checkImagesEnabled((ok) => {
      staticData.images_enabled = ok;
      enqueue("static", staticData);
      flush(); // IMPORTANT: flush immediately so you see it in DB
    });
  }

  // ---------- PERFORMANCE data (after page load) ----------
  function collectPerformance() {
    const nav = performance.getEntriesByType("navigation")[0];
    if (!nav) return;

    const start = nav.fetchStart ?? 0;

    const endCandidate =
      (nav.loadEventEnd && nav.loadEventEnd > 0) ? nav.loadEventEnd :
      (nav.domComplete && nav.domComplete > 0) ? nav.domComplete :
      (nav.responseEnd && nav.responseEnd > 0) ? nav.responseEnd :
      0;

    const end = endCandidate;
    const total = (end > 0 && end >= start) ? Math.round(end - start) : null;

    enqueue("performance", {
      timing_object: nav.toJSON ? nav.toJSON() : nav,
      start_loading: start,
      end_loading: end,
      total_load_ms: total
    });

    flush(); // IMPORTANT: flush immediately so you see it in DB
  }

  // ---------- ACTIVITY data ----------
  // Errors
  window.addEventListener(
    "error",
    (event) => {
      if (event instanceof ErrorEvent) {
        enqueue("error", {
          kind: "js-error",
          message: event.message,
          source: event.filename,
          line: event.lineno,
          column: event.colno,
          stack: event.error ? event.error.stack : ""
        });
      } else {
        const t = event.target;
        enqueue("error", {
          kind: "resource-error",
          tagName: t && t.tagName ? t.tagName : null,
          src: t && (t.src || t.href) ? (t.src || t.href) : null
        });
      }
    },
    true
  );

  window.addEventListener("unhandledrejection", (event) => {
    const r = event.reason;
    enqueue("error", {
      kind: "promise-rejection",
      message: r instanceof Error ? r.message : String(r),
      stack: r instanceof Error ? r.stack : ""
    });
  });

  // Idle tracking (2+ seconds no activity)
  let idleStart = null;
  let idleTimer = null;

  function startIdleTimer() {
    if (idleTimer) clearTimeout(idleTimer);
    idleTimer = setTimeout(() => {
      idleStart = Date.now();
      enqueue("idle_start", { idle_start_ms: idleStart });
    }, 2000);
  }

  function noteActivity() {
    // If we were idle, close that idle period now
    if (idleStart !== null) {
      const idleEnd = Date.now();
      enqueue("idle_end", {
        idle_start_ms: idleStart,
        idle_end_ms: idleEnd,
        idle_duration_ms: idleEnd - idleStart
      });
      idleStart = null;
    }
    startIdleTimer();
  }

  // Mouse activity (THROTTLED)
  let lastMouseTs = 0;
  document.addEventListener("mousemove", (e) => {
    noteActivity();
    const now = Date.now();
    if (now - lastMouseTs < 100) return; // sample ~10/sec
    lastMouseTs = now;
    enqueue("mouse_move", { x: e.clientX, y: e.clientY });
  });

  document.addEventListener("click", (e) => {
    noteActivity();
    enqueue("click", {
      x: e.clientX,
      y: e.clientY,
      button: e.button
    });
  });

  window.addEventListener(
    "scroll",
    () => {
      noteActivity();
      enqueue("scroll", { scrollX: window.scrollX, scrollY: window.scrollY });
    },
    { passive: true }
  );

  document.addEventListener("keydown", (e) => {
    noteActivity();
    enqueue("key", { action: "down", key: e.key });
  });

  document.addEventListener("keyup", (e) => {
    noteActivity();
    enqueue("key", { action: "up", key: e.key });
  });

  // Enter/Leave
  const enteredAt = Date.now();
  enqueue("enter", { entered_at_ms: enteredAt });
  flush();

  function recordLeave() {
    const leftAt = Date.now();
    enqueue("leave", {
      left_at_ms: leftAt,
      time_on_page_ms: leftAt - enteredAt
    });
    flush();
  }

  // visibilitychange handles tab switches; pagehide handles real navigation/unload
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "hidden") recordLeave();
  });

  window.addEventListener("pagehide", () => {
    recordLeave();
  });

  // ---------- boot ----------
  function bootAfterLoad() {
    // Start idle detection immediately (even if user never moves)
    startIdleTimer();

    collectStatic();
    collectPerformance();
  }

  if (document.readyState === "complete") {
    bootAfterLoad();
  } else {
    window.addEventListener("load", bootAfterLoad);
  }
})();
