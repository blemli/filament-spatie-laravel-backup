<div @if ($this->interval()) wire:poll.{{ $this->interval() }} @endif>
	{{ $this->table }}
</div>
