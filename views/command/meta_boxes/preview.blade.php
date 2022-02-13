<div class="wpsms-tw-metabox command-metabox preview-metabox">

    <div class="metabox-content">
        <div class="sms-preview-js" id="sms-preview">
            <div class="chatbox">
                <div class="top-bar">
                    <div class="avatar"><p>{{$siteName[0]}}</p></div>
                        <div class="name">
                            {{$wpsmsInstance->from}}
                            <!-- ({{WPSmsTwoWay\Core\Helper::ellipsis($siteName)}}) -->
                        </div>
                    <div class="icons">
                        <i class="fas fa-phone"></i>
                        <i class="fas fa-video"></i>
                    </div>
                    <div class="menu">
                        <div class="dots"></div>
                    </div>
                </div>
                <div class="middle">
                        <div class="outgoing">
                            <div class="bubble command"></div>
                        </div>
                        <div class="incoming">
                            <div class="bubble response" style="display: none;" >sample response</div>
                        </div>
                        <!-- <div class="typing">
                        <div class="bubble">
                            <div class="ellipsis one"></div>
                            <div class="ellipsis two"></div>
                            <div class="ellipsis three"></div>
                        </div>
                        </div> -->
                </div>
            </div>
        </div>
    </div>

</div>