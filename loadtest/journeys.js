// k6 load test (S22): realistic journeys in the mix we expect. Usage:
//   k6 run -e BASE_URL=https://staging.example -e PROFILE=smoke loadtest/journeys.js
// Profiles: smoke (CI, manual), load (sustained), peak (spike, e.g. a job alert), soak (hours).
import { sleep } from 'k6';

import { demoCitizen, signIn, think, visit } from './lib.js';

const profiles = {
    smoke: { executor: 'constant-vus', vus: 20, duration: '1m' },
    load: {
        executor: 'ramping-vus',
        startVUs: 0,
        stages: [
            { duration: '5m', target: Number(__ENV.VUS || 2000) },
            { duration: '20m', target: Number(__ENV.VUS || 2000) },
            { duration: '5m', target: 0 },
        ],
    },
    peak: {
        executor: 'ramping-arrival-rate',
        startRate: 100,
        timeUnit: '1s',
        preAllocatedVUs: 2000,
        maxVUs: Number(__ENV.VUS || 20000),
        stages: [
            { duration: '2m', target: Number(__ENV.RPS || 1000) },
            { duration: '5m', target: Number(__ENV.RPS || 1000) },
            { duration: '2m', target: 0 },
        ],
    },
    soak: { executor: 'constant-vus', vus: Number(__ENV.VUS || 500), duration: __ENV.DURATION || '2h' },
};

export const options = {
    scenarios: {
        young_people: { ...profiles[__ENV.PROFILE || 'smoke'], exec: 'youngPerson' },
        public_site: {
            ...profiles[__ENV.PROFILE || 'smoke'],
            exec: 'publicVisitor',
            ...(profiles[__ENV.PROFILE || 'smoke'].vus
                ? { vus: Math.max(1, Math.round(profiles[__ENV.PROFILE || 'smoke'].vus / 4)) }
                : {}),
        },
    },
    // The agreed targets (S22): p95 under 300 ms for young people's pages, errors under 0.1%.
    thresholds: {
        'http_req_duration{scenario:young_people}': ['p(95)<300'],
        'http_req_duration{scenario:public_site}': ['p(95)<300'],
        http_req_failed: ['rate<0.001'],
    },
};

export function youngPerson() {
    if (__ITER === 0) {
        sleep(Math.random() * 20); // people don't all sign in at the same second
    }
    signIn(demoCitizen());
    visit('/home');
    sleep(think());
    visit('/work/matches');
    sleep(think());
    visit('/work/jobs');
    sleep(think());
    visit('/learn/courses');
    sleep(think());
    visit('/learn/my');
    sleep(think());
    visit('/home/updates');
    sleep(think());
}

export function publicVisitor() {
    visit('/', false);
    sleep(think(2, 6));
    visit('/hubs', false);
    sleep(think(2, 6));
    visit('/help', false);
    sleep(think(2, 6));
    visit('/help/create-account', false);
    sleep(think(2, 6));
}
