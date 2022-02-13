<div class="submitbox" id="submitpost">
    <div class="misc-pub-section">
        <label class="wpsms-tw-metabox-lable">{{__('Status:', 'wp-sms-two-way')}}</label>
        <select name="command-status">
            @foreach(WPSmsTwoWay\Enums\CommandStatus::toArray() as $value => $label)
                <option value={{$value}} {{ selected($status, $value)}}>{{$label}}</option> 
            @endforeach
        </select>
    </div>
    <div id="major-publishing-actions">
        <div id="delete-action">
            <a class="submitdelete deletion" href="{!! get_delete_post_link($post) !!}">{{__('Delete', 'wp-sms-two-way')}}</a>
        </div>

        <div id="publishing-action">
            <span class="spinner"></span>
            <input name="post_status" type="hidden" value="publish">
            <input name="original_publish" type="hidden" id="original_publish" value="Update">
            <input type="submit" name="save" id="publish" class="button button-primary button-large" value="{{ $status ? __('Update Command', 'wp-sms-two-way') : __('Save Command', 'wp-sms-two-way')}}"></div>
        <div class="clear"></div>
    </div>
</div>
