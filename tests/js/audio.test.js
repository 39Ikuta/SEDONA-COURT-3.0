import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

test('alarm recordings are valid, distinct and loud PCM audio with the intended repeat lengths', () => {
    const recordings = [['stay-warning.wav', 8], ['checkout-due.wav', 4], ['grace-ended.wav', 2]];
    const buffers = recordings.map(([file, seconds]) => {
        const buffer = readFileSync(new URL(`../../public/sounds/${file}`, import.meta.url));
        assert.equal(buffer.toString('ascii', 0, 4), 'RIFF');
        assert.equal(buffer.toString('ascii', 8, 12), 'WAVE');
        assert.equal(buffer.readUInt16LE(20), 1);
        assert.equal(buffer.readUInt16LE(22), 1);
        assert.equal(buffer.readUInt32LE(24), 22050);
        assert.equal(buffer.readUInt16LE(34), 16);
        assert.equal(buffer.readUInt32LE(40), seconds * 22050 * 2);
        let peak = 0;
        for (let offset = 44; offset < buffer.length; offset += 2) {
            peak = Math.max(peak, Math.abs(buffer.readInt16LE(offset)) / 32768);
        }
        assert.ok(peak >= 0.89 && peak < 1, `${file} must be audible without clipping`);
        return buffer;
    });
    assert.equal(new Set(buffers.map(buffer => buffer.toString('base64'))).size, 3);
});

function audioHarness(play = () => Promise.resolve()) {
    const intervals = new Map();
    const oscillators = [];
    const gains = [];
    const listeners = {};
    class Audio {
        constructor(src) { this.src = src; this.paused = true; this.currentTime = 0; }
        play() { this.paused = false; return play(this); }
        pause() { this.paused = true; }
    }
    class AudioContext {
        constructor() { this.currentTime = 0; this.state = 'running'; this.destination = {}; }
        resume() { return Promise.resolve(); }
        createOscillator() {
            const oscillator = { frequency: { setValueAtTime(value) { oscillator.frequencyValue = value; } },
                connect() {}, disconnect() {}, start(at) { oscillator.startAt = at; },
                stop(at) { oscillator.stopAt = at; if (at === undefined) { oscillator.stopped = true; } } };
            oscillators.push(oscillator);
            return oscillator;
        }
        createGain() {
            const values = [];
            const gain = { gain: { setValueAtTime(value, at) { values.push([value, at]); },
                linearRampToValueAtTime(value, at) { values.push([value, at]); } }, connect() {}, disconnect() {}, values };
            gains.push(gain);
            return gain;
        }
    }
    const window = { AudioContext, addEventListener(name, action) { listeners[name] = action; }, removeEventListener() {} };
    const storage = { getItem() { return null; }, setItem() {} };
    const document = { getElementById() { return null; } };
    const source = readFileSync(new URL('../../resources/js/audio.js', import.meta.url), 'utf8').split('// Global instance')[0];
    runInNewContext(`${source}\nwindow.Engine = SedonaAudioEngine;`, {
        window, document, Audio, localStorage: storage, sessionStorage: storage, console,
        setInterval(action, delay) { const id = intervals.size + 1; intervals.set(id, { action, delay }); return id; },
        clearInterval(id) { intervals.delete(id); },
    });
    return { engine: new window.Engine(), intervals, oscillators, gains, listeners };
}

test('each checkout stage uses its own looping tune at full application volume', () => {
    const { engine } = audioHarness();
    const files = ['stay-warning.wav', 'checkout-due.wav', 'grace-ended.wav'];
    const played = [];
    for (const [index, mode] of ['stage1', 'stage2', 'stage3'].entries()) {
        engine.setSoundMode(mode);
        const audio = engine.alarmAudios[mode];
        assert.equal(audio.src, `/sounds/${files[index]}`);
        assert.equal(audio.loop, true);
        assert.equal(audio.volume, 1);
        assert.equal(audio.paused, false);
        for (const previous of played) { assert.equal(previous.paused, true); }
        played.push(audio);
    }
    engine.toggleMute();
    for (const audio of played) { assert.equal(audio.paused, true); }
});

test('synth fallback keeps recognizable rising, ding-dong, and urgent tunes with faster repeats', async () => {
    const { engine, intervals, oscillators, gains } = audioHarness(() => Promise.reject(new Error('Media unavailable')));
    const expectations = [
        ['stage1', [523.25, 659.25, 783.99, 1046.5], 8000],
        ['stage2', [783.99, 523.25, 783.99, 523.25], 4000],
        ['stage3', [1318.51, 1046.5, 1318.51, 1046.5, 1318.51, 1046.5], 2000],
    ];
    for (const [mode, notes, interval] of expectations) {
        const before = oscillators.length;
        engine.setSoundMode(mode);
        await Promise.resolve();
        assert.deepEqual(oscillators.slice(before).map(osc => osc.frequencyValue), notes);
        assert.equal(intervals.size, 1);
        assert.equal([...intervals.values()][0].delay, interval);
        assert.ok(gains.slice(before).every(gain => Math.max(...gain.values.map(([value]) => value)) >= 0.75));
    }
    engine.stopAllAlarms();
    assert.equal(intervals.size, 0);
    assert.ok(oscillators.every(osc => osc.stopped));
});

test('a late failed media request cannot restart a silenced or replaced alarm', async () => {
    const pending = [];
    const { engine, intervals, oscillators } = audioHarness(() => new Promise((resolve, reject) => pending.push({ resolve, reject })));
    engine.setSoundMode('stage1');
    engine.setSoundMode('stage2');
    pending[0].reject(new Error('Old request failed'));
    await Promise.resolve();
    assert.equal(oscillators.length, 0);
    assert.equal(intervals.size, 0);
    engine.stopAllAlarms();
    pending[1].reject(new Error('Silenced request failed'));
    await Promise.resolve();
    assert.equal(oscillators.length, 0);
    assert.equal(intervals.size, 0);
});

test('unlocking audio does not pause or mute a newly started checkout alarm', async () => {
    const pending = [];
    const { engine, listeners } = audioHarness(() => new Promise(resolve => pending.push(resolve)));
    listeners.click();
    engine.setSoundMode('stage1');
    for (const resolve of pending) { resolve(); }
    await Promise.resolve();
    assert.equal(engine.alarmAudios.stage1.paused, false);
    assert.equal(engine.alarmAudios.stage1.muted, false);
});
