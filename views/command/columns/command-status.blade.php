<div class="wpsms-tw-column">
    <span class="status-{{ $status }}">{{ WPSmsTwoWay\Enums\CommandStatus::tryFrom($status)->label ?? $status }}</span>
</div>