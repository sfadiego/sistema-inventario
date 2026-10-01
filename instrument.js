import * as Sentry from '@sentry/react';

const environment = import.meta.env.VITE_APP_ENV || '';
// El DSN se define en .env (VITE_SENTRY_DSN). Sin DSN, o en entorno local, Sentry queda desactivado.
const dsn = import.meta.env.VITE_SENTRY_DSN || '';
Sentry.init({
  dsn: environment !== 'local' ? dsn : '',
  // Setting this option to true will send default PII data to Sentry.
  // For example, automatic IP address collection on events
  sendDefaultPii: true,
  integrations: [Sentry.browserTracingIntegration()],
  // Tracing
  tracesSampleRate: 1.0, //  Capture 100% of the transactions
  // Set 'tracePropagationTargets' to control for which URLs distributed tracing should be enabled
  tracePropagationTargets: ['localhost'],
  // Enable logs to be sent to Sentry
  enableLogs: true,
});
