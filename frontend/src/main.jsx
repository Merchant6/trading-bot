import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import './styles.css';

const API_BASE = import.meta.env.VITE_API_BASE ?? 'http://127.0.0.1:8080';

function App() {
  const [status, setStatus] = useState(null);
  const [strategies, setStrategies] = useState([]);
  const [config, setConfig] = useState(null);
  const [form, setForm] = useState({
    symbol: 'BTCUSDT',
    interval: '5m',
    strategy: 'bollinger-rsi',
    paper_trading: true,
  });
  const [error, setError] = useState('');
  const formInitialized = useRef(false);

  async function request(path, options = {}) {
    const response = await fetch(`${API_BASE}${path}`, {
      headers: { 'Content-Type': 'application/json' },
      ...options,
    });

    if (!response.ok) {
      throw new Error(`Request failed: ${response.status}`);
    }

    return response.json();
  }

  async function refresh() {
    try {
      setError('');
      const [nextStatus, nextStrategies, nextConfig] = await Promise.all([
        request('/api/status'),
        request('/api/strategies'),
        request('/api/config'),
      ]);
      setStatus(nextStatus);
      setStrategies(nextStrategies.strategies ?? []);
      setConfig(nextConfig);
      if (!formInitialized.current) {
        setForm((current) => ({
          ...current,
          symbol: nextStatus.symbol ?? current.symbol,
          interval: nextStatus.interval ?? current.interval,
          strategy: nextStatus.strategy ?? current.strategy,
          paper_trading: nextStatus.paper_trading ?? current.paper_trading,
        }));
        formInitialized.current = true;
      }
    } catch (err) {
      setError(err.message);
    }
  }

  useEffect(() => {
    refresh();
    const timer = window.setInterval(refresh, 3000);
    return () => window.clearInterval(timer);
  }, []);

  async function startBot() {
    const nextStatus = await request('/api/bot/start', {
      method: 'POST',
      body: JSON.stringify(form),
    });
    setStatus(nextStatus);
  }

  async function stopBot() {
    const nextStatus = await request('/api/bot/stop', { method: 'POST' });
    setStatus(nextStatus);
  }

  const selectedStrategy = useMemo(
    () => strategies.find((strategy) => strategy.name === form.strategy),
    [strategies, form.strategy]
  );

  return (
    <main className="app-shell">
      <header className="topbar">
        <div>
          <h1>Trading Bot</h1>
          <p>{status?.symbol ?? form.symbol} / {status?.interval ?? form.interval}</p>
        </div>
        <div className={`status-pill ${status?.running ? 'running' : 'stopped'}`}>
          {status?.running ? 'Running' : 'Stopped'}
        </div>
      </header>

      {error && <div className="alert">API unavailable: {error}</div>}

      <section className="workspace">
        <div className="panel controls">
          <div className="panel-heading">
            <h2>Controls</h2>
            <div className="mode-toggle">
              <span>Paper</span>
              <label className="switch">
                <input
                  type="checkbox"
                  checked={form.paper_trading}
                  onChange={(event) => setForm({ ...form, paper_trading: event.target.checked })}
                />
                <span />
              </label>
            </div>
          </div>

          <label>
            <span>Symbol</span>
            <input
              value={form.symbol}
              onChange={(event) => setForm({ ...form, symbol: event.target.value.toUpperCase() })}
            />
          </label>

          <label>
            <span>Interval</span>
            <select value={form.interval} onChange={(event) => setForm({ ...form, interval: event.target.value })}>
              <option value="1m">1m</option>
              <option value="5m">5m</option>
              <option value="15m">15m</option>
              <option value="1h">1h</option>
              <option value="4h">4h</option>
            </select>
          </label>

          <label>
            <span>Strategy</span>
            <select value={form.strategy} onChange={(event) => setForm({ ...form, strategy: event.target.value })}>
              {strategies.map((strategy) => (
                <option key={strategy.name} value={strategy.name}>{strategy.name}</option>
              ))}
            </select>
          </label>

          <div className="button-row">
            <button className="primary" onClick={startBot}>Start</button>
            <button onClick={stopBot}>Stop</button>
          </div>
        </div>

        <div className="panel">
          <div className="panel-heading">
            <h2>Risk</h2>
          </div>
          <Metric label="Risk / trade" value={`${config?.risk?.risk_percentage ?? '-'}%`} />
          <Metric label="Max position" value={`${config?.risk?.max_position_percentage ?? '-'}%`} />
          <Metric label="Max leverage" value={`${config?.risk?.max_leverage ?? '-'}x`} />
          <Metric label="Take profit" value={`${config?.risk?.take_profit_percentage ?? '-'}%`} />
          <Metric label="Stop loss" value={`${config?.risk?.stop_loss_percentage ?? '-'}%`} />
        </div>

        <div className="panel strategy-panel">
          <div className="panel-heading">
            <h2>Strategy Settings</h2>
          </div>
          <div className="settings-grid">
            {Object.entries(selectedStrategy?.settings ?? {}).map(([key, value]) => (
              <Metric key={key} label={key} value={String(value)} />
            ))}
          </div>
        </div>

        <div className="panel chart-panel">
          <div className="panel-heading">
            <h2>Pair Chart</h2>
            <span className="chart-symbol">{form.symbol}</span>
          </div>
          <TradingViewChart symbol={form.symbol} interval={form.interval} />
        </div>

        <div className="panel event-panel">
          <div className="panel-heading">
            <h2>Event Feed</h2>
            <button className="ghost" onClick={refresh}>Refresh</button>
          </div>
          <div className="event-list">
            {(status?.events ?? []).slice(0, 12).map((event, index) => (
              <article key={`${event.created_at}-${index}`} className="event-row">
                <div>
                  <strong>{event.type}</strong>
                  <span>{event.created_at}</span>
                </div>
                <pre>{JSON.stringify(event.payload, null, 2)}</pre>
              </article>
            ))}
            {(status?.events ?? []).length === 0 && <p className="empty">No events yet.</p>}
          </div>
        </div>
      </section>
    </main>
  );
}

function TradingViewChart({ symbol, interval }) {
  const exchangeSymbol = `BINANCE:${normalizeSymbol(symbol)}`;
  const tvInterval = interval.replace('m', '').replace('h', '60');
  const params = new URLSearchParams({
    symbol: exchangeSymbol,
    interval: tvInterval,
    theme: 'light',
    style: '1',
    locale: 'en',
    toolbar_bg: '#ffffff',
    enable_publishing: 'false',
    hide_top_toolbar: 'false',
    hide_side_toolbar: 'false',
    allow_symbol_change: 'true',
    save_image: 'false',
    studies: 'RSI@tv-basicstudies',
  });

  return (
    <iframe
      key={`${exchangeSymbol}-${interval}`}
      className="chart-frame"
      title={`${exchangeSymbol} chart`}
      src={`https://www.tradingview.com/widgetembed/?${params.toString()}`}
      allowFullScreen
    />
  );
}

function normalizeSymbol(symbol) {
  return String(symbol || 'BTCUSDT').replace(/[^A-Z0-9]/gi, '').toUpperCase();
}

function Metric({ label, value }) {
  return (
    <div className="metric">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

createRoot(document.getElementById('root')).render(<App />);
