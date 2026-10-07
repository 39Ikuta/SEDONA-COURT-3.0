/**
 * Sedona Court Executive PMS - Audio Engine & Reactive 3-Stage Stay Alarm System
 * High-reliability dual-engine: HTML5 Audio with Web Audio API Synthesizer Fallback
 * 
 * 3-Stage Alarm Timeline:
 *  - 1st Alarm: 15 minutes before checkout (T - 15m) -> Ascending Harmonious Warning Chime
 *  - 2nd Alarm: Actual checkout time (T)              -> Continuous Digital Clock Alarm (alarm.mp3)
 *  - 3rd Alarm: 15 minutes after checkout (T + 15m)   -> High-Urgency Critical Overtime Alarm
 * 
 * Dynamic Recalibration on Xtend (Stay Extension):
 *  - Keyed by roomId/folioId + checkoutTime timestamp.
 *  - When extended, any active alarm immediately halts and all 3 stages reschedule to the new time.
 */

class SedonaAudioEngine {
    constructor() {
        this.audioCtx = null;
        this.isMuted = localStorage.getItem('sedona_audio_muted') === 'true';
        this.alarmAudio = null;
        this.kitchenAudio = null;

        // Current active sound mode: null | 'stage1' | 'stage2' | 'stage3'
        this.currentSoundMode = null;
        this.synthAlarmInterval = null;
        this.stage1ChimeInterval = null;
        this.stage1LastChimeTime = 0;

        // Snoozed rooms: { [roomId]: timestampUntil }
        this.snoozedRooms = {};

        // Silenced stages: Set of `${roomId}_${checkoutTimeISO}_stage${stageNum}`
        this.silencedStages = this.loadSilencedStages();

        this.initHtml5Audio();
        this.initAutoUnlock();
    }

    loadSilencedStages() {
        try {
            const stored = sessionStorage.getItem('sedona_silenced_alarm_stages');
            if (stored) {
                return new Set(JSON.parse(stored));
            }
        } catch (e) {}
        return new Set();
    }

    saveSilencedStages() {
        try {
            sessionStorage.setItem('sedona_silenced_alarm_stages', JSON.stringify([...this.silencedStages]));
        } catch (e) {}
    }

    initHtml5Audio() {
        try {
            this.alarmAudio = new Audio('/sounds/alarm.mp3');
            this.alarmAudio.loop = true;
            this.alarmAudio.volume = 0.85;

            this.kitchenAudio = new Audio('/sounds/kitchen-chime.mp3');
            this.kitchenAudio.volume = 0.9;
        } catch (e) {
            console.warn('[AudioEngine] HTML5 Audio init error:', e);
        }
    }

    initAutoUnlock() {
        const unlock = () => {
            this.ensureAudioContext();
            if (this.audioCtx && this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }
            // Prime HTML5 audio on first user touch/click
            if (this.alarmAudio) {
                this.alarmAudio.play().then(() => {
                    this.alarmAudio.pause();
                    this.alarmAudio.currentTime = 0;
                }).catch(() => {});
            }
            window.removeEventListener('click', unlock);
            window.removeEventListener('keydown', unlock);
            window.removeEventListener('touchstart', unlock);
        };

        window.addEventListener('click', unlock, { once: true });
        window.addEventListener('keydown', unlock, { once: true });
        window.addEventListener('touchstart', unlock, { once: true });
    }

    ensureAudioContext() {
        if (!this.audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                this.audioCtx = new AudioContextClass();
            }
        }
        if (this.audioCtx && this.audioCtx.state === 'suspended') {
            this.audioCtx.resume();
        }
    }

    toggleMute() {
        this.isMuted = !this.isMuted;
        localStorage.setItem('sedona_audio_muted', this.isMuted ? 'true' : 'false');
        if (this.isMuted) {
            this.stopAllAlarms();
        }
        this.updateAudioButtonState();
        return this.isMuted;
    }

    updateAudioButtonState() {
        const btn = document.getElementById('soundToggleBtn');
        const icon = document.getElementById('soundToggleIcon');
        const text = document.getElementById('soundToggleText');
        if (btn && icon && text) {
            if (this.isMuted) {
                btn.classList.remove('bg-emerald-800', 'text-emerald-100');
                btn.classList.add('bg-slate-700', 'text-slate-300');
                icon.textContent = '🔇';
                text.textContent = 'Muted';
            } else {
                btn.classList.remove('bg-slate-700', 'text-slate-300');
                btn.classList.add('bg-emerald-800', 'text-emerald-100');
                icon.textContent = '🔊';
                text.textContent = 'Sound ON';
            }
        }
    }

    /* -------------------------------------------------------------------------
     * SOUND ENGINE DISPATCHER (Stage 1, Stage 2, Stage 3)
     * ------------------------------------------------------------------------- */

    /**
     * Set the desired sound mode. Priority: stage3 > stage2 > stage1 > null
     */
    setSoundMode(mode) {
        if (this.isMuted) {
            this.stopAllAlarms();
            return;
        }

        if (this.currentSoundMode === mode) {
            // Already running in this mode
            return;
        }

        // Stop current sounds before switching
        this.stopAllAlarms();
        this.currentSoundMode = mode;

        if (mode === 'stage3') {
            this.startStage3Sound();
        } else if (mode === 'stage2') {
            this.startStage2Sound();
        } else if (mode === 'stage1') {
            this.startStage1Sound();
        }
    }

    /**
     * Stop all active alarm sounds
     */
    stopAllAlarms() {
        this.currentSoundMode = null;

        // Stop HTML5 audio
        if (this.alarmAudio) {
            try {
                this.alarmAudio.pause();
                this.alarmAudio.currentTime = 0;
            } catch (e) {}
        }

        // Stop Synthesizer intervals
        if (this.synthAlarmInterval) {
            clearInterval(this.synthAlarmInterval);
            this.synthAlarmInterval = null;
        }

        if (this.stage1ChimeInterval) {
            clearInterval(this.stage1ChimeInterval);
            this.stage1ChimeInterval = null;
        }
    }

    // Alias for legacy buttons
    stopDigitalAlarm() {
        this.stopAllAlarms();
    }

    /**
     * Stage 1: 15-Minute Warning Chime
     * Plays ascending melodic chime [C5, E5, G5, C6] and gently repeats every 30s
     */
    startStage1Sound() {
        this.playStage1Chime();
        this.stage1ChimeInterval = setInterval(() => {
            if (this.currentSoundMode === 'stage1' && !this.isMuted) {
                this.playStage1Chime();
            }
        }, 30000);
    }

    playStage1Chime() {
        if (this.isMuted) return;
        this.ensureAudioContext();

        try {
            if (this.audioCtx) {
                // Melodious C-Major Chime: C5 (523.25Hz), E5 (659.25Hz), G5 (783.99Hz), C6 (1046.50Hz)
                const tones = [523.25, 659.25, 783.99, 1046.50];
                const now = this.audioCtx.currentTime;

                tones.forEach((freq, idx) => {
                    const osc = this.audioCtx.createOscillator();
                    const gain = this.audioCtx.createGain();

                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, now + idx * 0.15);

                    gain.gain.setValueAtTime(0, now + idx * 0.15);
                    gain.gain.linearRampToValueAtTime(0.28, now + idx * 0.15 + 0.04);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.15 + 0.55);

                    osc.connect(gain);
                    gain.connect(this.audioCtx.destination);

                    osc.start(now + idx * 0.15);
                    osc.stop(now + idx * 0.15 + 0.56);
                });
            }
        } catch (e) {
            console.warn('[AudioEngine] Stage 1 chime error:', e);
        }
    }

    /**
     * Stage 2: Actual Checkout Time Alarm
     * Continuous digital clock alarm (alarm.mp3) with Web Audio fallback
     */
    startStage2Sound() {
        if (this.alarmAudio) {
            this.alarmAudio.currentTime = 0;
            const playPromise = this.alarmAudio.play();
            if (playPromise !== undefined) {
                playPromise.catch((err) => {
                    console.warn('[AudioEngine] Stage 2 HTML5 alarm blocked, using Web Audio synth:', err);
                    this.startStandardSynthAlarm();
                });
            }
        } else {
            this.startStandardSynthAlarm();
        }
    }

    startStandardSynthAlarm() {
        if (this.synthAlarmInterval) return;
        this.ensureAudioContext();

        const triggerBeep = () => {
            if (this.isMuted || !this.currentSoundMode) return;
            try {
                this.ensureAudioContext();
                if (!this.audioCtx) return;

                const osc = this.audioCtx.createOscillator();
                const gain = this.audioCtx.createGain();

                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(880, this.audioCtx.currentTime); // A5
                osc.frequency.exponentialRampToValueAtTime(1200, this.audioCtx.currentTime + 0.12);

                gain.gain.setValueAtTime(0.3, this.audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, this.audioCtx.currentTime + 0.15);

                osc.connect(gain);
                gain.connect(this.audioCtx.destination);

                osc.start();
                osc.stop(this.audioCtx.currentTime + 0.16);
            } catch (e) {}
        };

        triggerBeep();
        this.synthAlarmInterval = setInterval(triggerBeep, 450);
    }

    /**
     * Stage 3: 15-Minute Overtime Critical Alarm
     * Dual-layer: Continuous digital alarm + High-frequency urgent double-beep synth
     */
    startStage3Sound() {
        // Also run HTML5 alarm in background if available
        if (this.alarmAudio) {
            this.alarmAudio.currentTime = 0;
            this.alarmAudio.play().catch(() => {});
        }

        if (this.synthAlarmInterval) return;
        this.ensureAudioContext();

        let toggle = false;
        const triggerUrgentTone = () => {
            if (this.isMuted || !this.currentSoundMode) return;
            try {
                this.ensureAudioContext();
                if (!this.audioCtx) return;

                const now = this.audioCtx.currentTime;
                const osc = this.audioCtx.createOscillator();
                const gain = this.audioCtx.createGain();

                // Rapid alternating dual-pitch urgent pulses: 1046Hz (C6) and 1318Hz (E6)
                osc.type = 'sawtooth';
                const freq = toggle ? 1318.51 : 1046.50;
                toggle = !toggle;

                osc.frequency.setValueAtTime(freq, now);
                gain.gain.setValueAtTime(0.35, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.12);

                osc.connect(gain);
                gain.connect(this.audioCtx.destination);

                osc.start(now);
                osc.stop(now + 0.13);
            } catch (e) {}
        };

        triggerUrgentTone();
        this.synthAlarmInterval = setInterval(triggerUrgentTone, 220); // Fast 220ms cadence
    }

    /* -------------------------------------------------------------------------
     * KITCHEN BELL & OTHER CHIMES
     * ------------------------------------------------------------------------- */
    playKitchenChime() {
        if (this.isMuted) return;

        if (this.kitchenAudio) {
            this.kitchenAudio.currentTime = 0;
            this.kitchenAudio.play().catch(() => {
                this.synthesizeKitchenBell();
            });
        } else {
            this.synthesizeKitchenBell();
        }
    }

    synthesizeKitchenBell() {
        this.ensureAudioContext();
        if (!this.audioCtx) return;

        try {
            const chords = [587.33, 739.99, 880.00, 1174.66];
            const now = this.audioCtx.currentTime;

            chords.forEach((freq, idx) => {
                const osc = this.audioCtx.createOscillator();
                const gain = this.audioCtx.createGain();

                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, now + idx * 0.12);

                gain.gain.setValueAtTime(0, now + idx * 0.12);
                gain.gain.linearRampToValueAtTime(0.3, now + idx * 0.12 + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.12 + 0.6);

                osc.connect(gain);
                gain.connect(this.audioCtx.destination);

                osc.start(now + idx * 0.12);
                osc.stop(now + idx * 0.12 + 0.62);
            });
        } catch (e) {}
    }

    /* -------------------------------------------------------------------------
     * SNOOZE, SILENCE, AND STATE CONTROLS
     * ------------------------------------------------------------------------- */

    /**
     * Snooze room for X minutes (default 5m)
     */
    snoozeRoom(roomId, minutes = 5) {
        this.snoozedRooms[roomId] = Date.now() + (minutes * 60 * 1000);
        this.evaluateAlarms();
    }

    isRoomSnoozed(roomId) {
        const snoozeUntil = this.snoozedRooms[roomId];
        if (!snoozeUntil) return false;
        if (Date.now() < snoozeUntil) return true;
        delete this.snoozedRooms[roomId];
        return false;
    }

    /**
     * Silence a specific stage for a room stay (e.g. Stage 1 silenced, but Stage 2 will still ring!)
     */
    silenceStage(roomId, checkoutTimeStr, stageNum) {
        const key = `${roomId}_${checkoutTimeStr}_stage${stageNum}`;
        this.silencedStages.add(key);
        this.saveSilencedStages();
        this.evaluateAlarms();
    }

    isStageSilenced(roomId, checkoutTimeStr, stageNum) {
        const key = `${roomId}_${checkoutTimeStr}_stage${stageNum}`;
        return this.silencedStages.has(key);
    }

    /* -------------------------------------------------------------------------
     * MAIN EVALUATION LOOP (Run every 1,000ms)
     * ------------------------------------------------------------------------- */
    evaluateAlarms() {
        // Query occupied room rows or cards across table and board views
        const roomElements = document.querySelectorAll('[data-room-id][data-checkout-time]');
        const now = Date.now();

        let highestActiveStage = null; // 3 > 2 > 1 > null
        const activeAlarmRooms = [];

        roomElements.forEach(el => {
            const roomId = el.getAttribute('data-room-id');
            const roomNumber = el.getAttribute('data-room-number') || roomId;
            const guestName = el.getAttribute('data-guest-name') || 'Guest';
            const checkoutTimeStr = el.getAttribute('data-checkout-time');
            const folioId = el.getAttribute('data-folio-id');
            const timerCell = el.querySelector('.stay-countdown-timer');

            if (!checkoutTimeStr) return;

            const checkoutTime = new Date(checkoutTimeStr).getTime();
            if (isNaN(checkoutTime)) return;

            const remainingMs = checkoutTime - now;
            const overdueMs = now - checkoutTime;
            const isSnoozed = this.isRoomSnoozed(roomId);

            /* -----------------------------------------------------------------
             * Determine Alarm Stage:
             *  - Stage 3: overdueMs >= 15 * 60 * 1000 (15m AFTER checkout)
             *  - Stage 2: remainingMs <= 0 && overdueMs < 15 * 60 * 1000 (ACTUAL time up to 15m)
             *  - Stage 1: remainingMs <= 15 * 60 * 1000 && remainingMs > 0 (15m BEFORE checkout)
             *  - Normal:  remainingMs > 15 * 60 * 1000
             * ----------------------------------------------------------------- */
            let currentStage = null;

            if (overdueMs >= 15 * 60 * 1000) {
                currentStage = 3;
            } else if (remainingMs <= 0) {
                currentStage = 2;
            } else if (remainingMs <= 15 * 60 * 1000) {
                currentStage = 1;
            }

            // Update UI timer cell if present
            if (timerCell) {
                if (currentStage === 3) {
                    const totalSecs = Math.floor(overdueMs / 1000);
                    const overHrs = Math.floor(totalSecs / 3600);
                    const overMins = Math.floor((totalSecs % 3600) / 60);
                    const overFormatted = (overHrs > 0 ? `+${overHrs}h ` : '+') + `${overMins}m Critical`;
                    timerCell.innerHTML = `<span class="inline-flex items-center gap-1 text-rose-950 bg-rose-200 border-2 border-rose-500 px-1.5 py-0.5 rounded font-black font-mono text-[10px] animate-pulse">🚨 ${overFormatted}</span>`;
                } else if (currentStage === 2) {
                    const totalSecs = Math.floor(overdueMs / 1000);
                    const overMins = Math.floor(totalSecs / 60);
                    const overSecs = totalSecs % 60;
                    const overFormatted = `+${overMins}m ${overSecs < 10 ? '0' : ''}${overSecs}s Due`;
                    timerCell.innerHTML = `<span class="inline-flex items-center gap-1 text-purple-950 bg-purple-200 border border-purple-400 px-1.5 py-0.5 rounded font-bold font-mono text-[10px] animate-pulse">⏰ ${overFormatted}</span>`;
                } else if (currentStage === 1) {
                    const totalSecs = Math.floor(remainingMs / 1000);
                    const mins = Math.floor(totalSecs / 60);
                    const secs = totalSecs % 60;
                    const formatted = `${mins}m ${secs < 10 ? '0' : ''}${secs}s (Due Soon)`;
                    timerCell.innerHTML = `<span class="inline-flex items-center gap-1 text-amber-800 bg-amber-100 border border-amber-300 px-1.5 py-0.5 rounded font-bold font-mono text-[10px] animate-pulse">⏱ ${formatted}</span>`;
                } else {
                    const totalSecs = Math.floor(remainingMs / 1000);
                    const hrs = Math.floor(totalSecs / 3600);
                    const mins = Math.floor((totalSecs % 3600) / 60);
                    const secs = totalSecs % 60;
                    const formatted = (hrs > 0 ? `${hrs}h ` : '') + `${mins}m ${secs < 10 ? '0' : ''}${secs}s`;
                    timerCell.innerHTML = `<span class="text-slate-700 font-mono text-[11px]">${formatted}</span>`;
                }
            }

            // Check if this room has an active alarm stage that needs staff alert
            if (currentStage !== null) {
                const isSilenced = this.isStageSilenced(roomId, checkoutTimeStr, currentStage);

                // Format stage details for banner
                let stageLabel = '';
                let stageBadgeClass = '';
                let timeSummary = '';

                if (currentStage === 1) {
                    const totalSecs = Math.floor(remainingMs / 1000);
                    const mins = Math.floor(totalSecs / 60);
                    const secs = totalSecs % 60;
                    stageLabel = '1ST ALARM: 15M REMINDER';
                    stageBadgeClass = 'bg-amber-500 text-white';
                    timeSummary = `Due in ${mins}m ${secs < 10 ? '0' : ''}${secs}s`;
                } else if (currentStage === 2) {
                    const totalSecs = Math.floor(overdueMs / 1000);
                    const mins = Math.floor(totalSecs / 60);
                    const secs = totalSecs % 60;
                    stageLabel = '2ND ALARM: DUE NOW';
                    stageBadgeClass = 'bg-purple-600 text-white';
                    timeSummary = `Due Now (+${mins}m ${secs < 10 ? '0' : ''}${secs}s)`;
                } else if (currentStage === 3) {
                    const totalSecs = Math.floor(overdueMs / 1000);
                    const hrs = Math.floor(totalSecs / 3600);
                    const mins = Math.floor((totalSecs % 3600) / 60);
                    stageLabel = '3RD ALARM: +15M CRITICAL OVERTIME';
                    stageBadgeClass = 'bg-rose-600 text-white animate-pulse';
                    timeSummary = `${(hrs > 0 ? `${hrs}h ` : '') + `${mins}m`} Overtime (Excess Hour Applies)`;
                }

                // Banner displays all rooms currently in an alarm stage
                activeAlarmRooms.push({
                    roomId,
                    roomNumber,
                    guestName,
                    folioId,
                    checkoutTimeStr,
                    currentStage,
                    stageLabel,
                    stageBadgeClass,
                    timeSummary,
                    isSnoozed,
                    isSilenced
                });

                // Audio triggers if NOT snoozed and NOT silenced for this specific stage
                if (!isSnoozed && !isSilenced) {
                    if (highestActiveStage === null || currentStage > highestActiveStage) {
                        highestActiveStage = currentStage;
                    }
                }
            }
        });

        // Update Audio Playback based on highest active stage
        if (highestActiveStage === 3) {
            this.setSoundMode('stage3');
        } else if (highestActiveStage === 2) {
            this.setSoundMode('stage2');
        } else if (highestActiveStage === 1) {
            this.setSoundMode('stage1');
        } else {
            this.setSoundMode(null);
        }

        // Update Alarm Banner UI
        this.updateAlarmBanner(activeAlarmRooms, highestActiveStage);
    }

    updateAlarmBanner(activeAlarmRooms, highestActiveStage) {
        const banner = document.getElementById('activeAlarmBanner');
        const bannerList = document.getElementById('alarmBannerRoomsList');
        const bannerTitle = document.getElementById('alarmBannerTitle');
        const bannerIcon = document.getElementById('alarmBannerIcon');

        if (!banner || !bannerList) return;

        if (activeAlarmRooms.length > 0) {
            banner.classList.remove('hidden');

            // Banner styling and title based on highest stage
            if (highestActiveStage === 3) {
                banner.className = 'bg-rose-950 border-2 border-rose-500 text-white p-3 mx-1 shadow-xl rounded-lg';
                if (bannerTitle) bannerTitle.textContent = '🚨 3RD ALARM: CRITICAL GUEST OVERTIME DETECTED (+15M EXCEEDED)';
                if (bannerIcon) bannerIcon.textContent = '🚨';
            } else if (highestActiveStage === 2) {
                banner.className = 'bg-purple-950 border-2 border-purple-500 text-white p-3 mx-1 shadow-lg rounded-lg';
                if (bannerTitle) bannerTitle.textContent = '⏰ 2ND ALARM: GUEST CHECKOUT DUE NOW';
                if (bannerIcon) bannerIcon.textContent = '⏰';
            } else if (highestActiveStage === 1) {
                banner.className = 'bg-amber-950 border-2 border-amber-500 text-white p-3 mx-1 shadow-md rounded-lg';
                if (bannerTitle) bannerTitle.textContent = '⚠️ 1ST ALARM: UPCOMING CHECKOUT REMINDER (15 MINUTES)';
                if (bannerIcon) bannerIcon.textContent = '⏱';
            } else {
                // All active rooms are either snoozed or silenced
                banner.className = 'bg-slate-900 border-2 border-slate-600 text-slate-200 p-3 mx-1 shadow rounded-lg';
                if (bannerTitle) bannerTitle.textContent = '🔔 ACTIVE STAY MONITOR (ALL ALARMS SNOOZED / SILENCED)';
                if (bannerIcon) bannerIcon.textContent = '🔕';
            }

            bannerList.innerHTML = activeAlarmRooms.map(r => {
                const statusNotes = r.isSnoozed ? '<span class="text-amber-400 font-bold ml-1.5">[Snoozed]</span>' :
                                   (r.isSilenced ? '<span class="text-slate-400 font-bold ml-1.5">[Silenced]</span>' : '');

                return `
                    <div class="flex flex-wrap items-center justify-between bg-white text-slate-900 px-3 py-2 border border-slate-300 rounded shadow-sm text-xs gap-2">
                        <div class="flex items-center space-x-2 flex-wrap">
                            <span class="px-2 py-0.5 font-black text-[10px] rounded uppercase ${r.stageBadgeClass}">
                                ${r.stageLabel}
                            </span>
                            <strong class="font-mono text-sm font-black text-slate-900">ROOM ${r.roomNumber}</strong>
                            <span class="text-slate-400">&bull;</span>
                            <span class="text-slate-700 font-medium">${r.guestName}</span>
                            <span class="text-slate-400">&bull;</span>
                            <span class="font-bold font-mono text-purple-900">${r.timeSummary}</span>
                            ${statusNotes}
                        </div>
                        <div class="flex items-center space-x-1.5 ml-auto">
                            ${r.folioId ? `
                                <button type="button" onclick="openXtendModal(${r.folioId}, '${r.roomNumber}')" class="px-2.5 py-1 bg-sky-600 hover:bg-sky-700 text-white font-black text-[11px] rounded shadow-sm" title="Extend Stay (Recalibrates Alarms)">
                                    + Xtend
                                </button>
                            ` : ''}
                            <button type="button" onclick="sedonaAudio.snoozeRoom('${r.roomId}', 5)" class="px-2 py-1 bg-amber-600 hover:bg-amber-700 text-white font-bold text-[10px] rounded shadow-sm" title="Snooze for 5 minutes">
                                Snooze 5m
                            </button>
                            <button type="button" onclick="sedonaAudio.silenceStage('${r.roomId}', '${r.checkoutTimeStr}', ${r.currentStage})" class="px-2 py-1 bg-slate-600 hover:bg-slate-700 text-white font-bold text-[10px] rounded shadow-sm" title="Silence this alarm stage">
                                Silence
                            </button>
                            ${r.folioId ? `
                                <a href="/checkout/${r.folioId}" class="px-2.5 py-1 bg-rose-700 hover:bg-rose-800 text-white font-black text-[11px] rounded shadow-sm">
                                    Checkout
                                </a>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            this.setSoundMode(null);
            banner.classList.add('hidden');
        }
    }
}

// Global instance
window.sedonaAudio = new SedonaAudioEngine();

// Auto-run reactive loop every 1,000ms on pages with room countdowns
setInterval(() => {
    if (window.sedonaAudio) {
        window.sedonaAudio.evaluateAlarms();
    }
}, 1000);

