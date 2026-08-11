# Kohan avatar mood map

Kohan is a single avatar with a validated v2 atlas. The installed runtime controller extends the host's nine native animation states and sixteen pointer-driven look directions with approved supplemental moods, random click actions, idle scheduling, and walking patrol. Pointer enter never forces `jumping`.

## Native moods

| Mood | Runtime state | Kohan behavior |
| --- | --- | --- |
| Calm | `idle` | Quiet breathing and blinking. |
| Moving right | `running-right` | Runs or moves toward screen-right. |
| Moving left | `running-left` | Runs or moves toward screen-left. |
| Friendly | `waving` | Hello, acknowledgement, and goodbye fallback. |
| Excited | `jumping` | Playful jump. |
| Annoyed | `failed` | Angry or blocked reaction. |
| Confused | `waiting` | Vision-headset pose while waiting for input. |
| Focused | `running` | Works on the Apple-logo MacBook. |
| Reviewing | `review` | Reviews work on the Apple-logo iPad. |
| Looking | pointer look | Sixteen clockwise attention directions. |
| Walking patrol | `running-right` → `running-left` | Five short steps out and five steps back using only the approved atlas. |

## Supplemental moods

The package also keeps dedicated approved artwork for wink, angry still, confused Vision mode, Colt Kodiak low-ready, Russian Roulette (a fictional, non-graphic six-frame camera-facing sequence), drag-annoyed, fall-scared, and wave-snap-smoke goodbye. These are declared in `moods.json` with native fallbacks. In this artwork bundle the Russian Roulette mood uses `../supplemental-artwork/chamber-game-camera.png`; the older `chamber-camera` name remains as a compatibility alias.

`moods.json` is the avatar integration manifest. The installed Codex runtime patch selects Kohan when no other avatar is selected and wakes it once per app session. The website integration uses `window.KohanAvatar`.

## Idle controller

`kohan-idle-controller.js` waits a random 30–75 seconds between idle activities. It plays a small random approved action on ordinary intervals. Every third idle interval it plays `russian-roulette`, randomly ends on either `fire` or `dry-click`, and returns to `idle`. A fire outcome is followed by `fall-scared` and then the `confused-vision` IDK reaction. The controller pauses while the avatar is busy and requires a host implementation of `window.KohanAvatar.play(mood, options)`.

Hover has no action and must never trigger `jumping`; it may only update pointer-look. A real click on `[data-kohan-avatar]`, or the `kohan:avatar:click` event, chooses one random click action and returns to idle. Dragging must suppress the click event.

## Identity lock

The approved atlas and existing supplemental files are the only visual sources. Do not mix in, regenerate, redraw, or face-swap rejected variants. When an exact pose is unavailable, use the native fallback so Kohan's face, hair, proportions, clothing, and scale stay consistent.
