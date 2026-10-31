<?php
declare(strict_types=1);

namespace Stillroom;

/** The 33 procedural background scenes (assets/js/backgrounds.js) and the 33 built-in generative sounds (assets/js/presets.js). */
final class Scenes
{
    public const SCENES = [
        "stress-relief" => ["Stress relief", "Moods", "Let the weight fall away"],
        "deep-relaxation" => ["Deep relaxation", "Moods", "A slower kind of space"],
        "deep-sleep" => ["Deep sleep", "Moods", "Drift beyond the day"],
        "anxiety-calm" => ["Anxiety calm", "Moods", "Nothing to hold onto"],
        "meditation" => ["Meditation", "Moods", "Return to the present"],
        "focus" => ["Focus", "Moods", "One clear direction"],
        "lo-fi-chill" => ["Lo-fi chill", "Moods", "Somewhere after midnight"],
        "calm-piano" => ["Calm piano", "Moods", "Notes across still water"],
        "spa-yoga" => ["Spa & yoga", "Moods", "Breathe into balance"],
        "gentle-morning" => ["Gentle morning", "Moods", "A quiet beginning"],
        "creative-flow" => ["Creative flow", "Moods", "Follow the unexpected"],
        "delta" => ["Delta sleep", "Binaural", "2 Hz · Deep sleep"],
        "theta" => ["Theta meditation", "Binaural", "6 Hz · Inner stillness"],
        "alpha" => ["Alpha calm", "Binaural", "10 Hz · Relaxed awareness"],
        "beta" => ["Beta focus", "Binaural", "16 Hz · Steady attention"],
        "gamma" => ["Gamma clarity", "Binaural", "40 Hz · Clear thinking"],
        "hz-174" => ["174 Hz", "Frequencies", "Grounding"],
        "hz-285" => ["285 Hz", "Frequencies", "Restoration"],
        "hz-396" => ["396 Hz", "Frequencies", "Release"],
        "hz-417" => ["417 Hz", "Frequencies", "Change"],
        "hz-432" => ["432 Hz", "Frequencies", "Natural tuning"],
        "hz-528" => ["528 Hz", "Frequencies", "Love frequency"],
        "hz-639" => ["639 Hz", "Frequencies", "Connection"],
        "hz-741" => ["741 Hz", "Frequencies", "Clarity"],
        "hz-852" => ["852 Hz", "Frequencies", "Intuition"],
        "hz-963" => ["963 Hz", "Frequencies", "Awareness"],
        "rain" => ["Rain", "Nature & noise", "Window rain"],
        "ocean" => ["Ocean waves", "Nature & noise", "One wave / 14s"],
        "forest" => ["Forest night", "Nature & noise", "Crickets · Breeze"],
        "stream" => ["Mountain stream", "Nature & noise", "Running water"],
        "brown-noise" => ["Brown noise", "Nature & noise", "A deep, soft rumble"],
        "pink-noise" => ["Pink noise", "Nature & noise", "An even, gentle hush"],
        "white-noise" => ["White noise", "Nature & noise", "A blanket of sound"],
    ];

    /** engine id => [name, default scene] */
    public const ENGINES = [
        "stress-relief" => ["Stress relief", "stress-relief"],
        "relaxation" => ["Deep relaxation", "deep-relaxation"],
        "deep-sleep" => ["Deep sleep", "deep-sleep"],
        "anxiety-calm" => ["Anxiety calm", "anxiety-calm"],
        "meditation" => ["Meditation", "meditation"],
        "focus" => ["Focus", "focus"],
        "lofi" => ["Lo-fi chill", "lo-fi-chill"],
        "calm-piano" => ["Calm piano", "calm-piano"],
        "spa" => ["Spa & yoga", "spa-yoga"],
        "morning" => ["Gentle morning", "gentle-morning"],
        "creative-flow" => ["Creative flow", "creative-flow"],
        "delta" => ["Delta sleep", "delta"],
        "theta" => ["Theta meditation", "theta"],
        "alpha" => ["Alpha calm", "alpha"],
        "beta" => ["Beta focus", "beta"],
        "gamma" => ["Gamma clarity", "gamma"],
        "174hz" => ["174 Hz", "hz-174"],
        "285hz" => ["285 Hz", "hz-285"],
        "396hz" => ["396 Hz", "hz-396"],
        "417hz" => ["417 Hz", "hz-417"],
        "432hz" => ["432 Hz", "hz-432"],
        "528hz" => ["528 Hz", "hz-528"],
        "639hz" => ["639 Hz", "hz-639"],
        "741hz" => ["741 Hz", "hz-741"],
        "852hz" => ["852 Hz", "hz-852"],
        "963hz" => ["963 Hz", "hz-963"],
        "rain" => ["Rain", "rain"],
        "ocean" => ["Ocean waves", "ocean"],
        "forest-night" => ["Forest night", "forest"],
        "stream" => ["Mountain stream", "stream"],
        "brown-noise" => ["Brown noise", "brown-noise"],
        "pink-noise" => ["Pink noise", "pink-noise"],
        "white-noise" => ["White noise", "white-noise"],
    ];

    public static function isScene(string $id): bool { return isset(self::SCENES[$id]); }
    public static function isEngine(string $id): bool { return isset(self::ENGINES[$id]); }
}
