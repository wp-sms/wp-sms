<div class="wpsms-tw-metabox command-metabox actions-metabox">
<table>
    <tr class="actions-row">
        <th class="row-info">
            <span class="title">{{__('Actions', 'wp-sms-two-way')}}</span>
            <p class="description">{{__('Please select which action to be fired when the command is called.', 'wp-sms-two-way')}}</p>
        </th>
        <td class="row-content">
            <div class="field">
                <label for="wpsms-tw-command-action-select">{{__('Command Action', 'wp-sms-two-way')}}</label>
                <select name="command-action" id="wpsms-tw-command-action-select">
                    @foreach($allActions as $className => $class)
                        <optgroup label="{{$class->getDescription()}}">
                            @foreach($class->getActions() as $actionName => $action)
                                @php $currentAction = "$className/$actionName" @endphp
                                <option value="{{$currentAction}}" {{ selected($selectedAction, $currentAction) }} {{ disabled($class->isActive(), false) }}>
                                    {{__($action->getDescription(), 'wp-sms-two-way')}}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="wpsms-tw-command-text">{{__('Command Name', 'wp-sms-two-way')}}</label>
                <input type="text" name="command-name" id="wpsms-tw-command-name" maxlength ='30' value="{{$commandName}}">
            </div>
        </td>
    </tr>
</table>
</div>
