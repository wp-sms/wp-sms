<div class="wpsms-tw-metabox command-metabox response-metabox">
    <table>
        <tr class="response-row">
            <th class="row-info">
                <span class="title">{{__('Success Response', 'wp-sms-two-way')}}</span>
                <p class="description">{{__('When a command is successfully executed, enter a message that you want to reply back to the number.', 'wp-sms-two-way')}}</p>
            </th>
            <td class="row-content">
                <input type="checkbox" name="" id="wpsms-tw-success-response-checkbox" @if( ($responseData->success->status ?? null) == 'enabled' ) checked @endif>
                <label for="wpsms-tw-success-response-checkbox">{{__('Success Response', 'wp-sms-two-way')}}</label>
                <textarea name="command-response[success][text]" id="wpsms-tw-success-response-textarea" cols="10" rows="5" placeholder="{{__('Your request has been received and processed successfully.', 'wp-sms-two-way')}}">{{$responseData->success->text ?? null}}</textarea>
            </td>
        </tr>
        <tr class="response-row">
            <th class="row-info">
                <span class="title">{{__('Failure Response', 'wp-sms-two-way')}}</span>
                <p class="description">{{__('Enter a message that you want to reply back to the number when the command could not process.', 'wp-sms-two-way')}}</p>
            </th>
            <td class="row-content">
                <input type="checkbox" name="" id="wpsms-tw-failure-response-checkbox" @if( ($responseData->failure->status ?? null) == 'enabled' ) checked @endif>
                <label for="wpsms-tw-failure-response-checkbox">{{__('Failure Response', 'wp-sms-two-way')}}</label>
                <textarea name="command-response[failure][text]" id="wpsms-tw-failure-response-textarea" cols="10" rows="5" placeholder="{{__('The request could not processed, please make sure you sent the correct command.', 'wp-sms-two-way')}}">{{$responseData->failure->text ?? null}}</textarea>
            </td>
        </tr>
    </table>
</div>
