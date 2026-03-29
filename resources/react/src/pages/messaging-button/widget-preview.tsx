import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Monitor } from 'lucide-react';
import { isRtl } from '@/hooks/use-is-rtl';
import type { MessagingButtonSettings } from './use-mb-settings';

interface WidgetPreviewProps {
  settings: MessagingButtonSettings;
}

/**
 * Pure DOM preview — no external CDN, no framework.
 * Renders a miniature version of the widget inside an iframe using plain JS.
 */
const PREVIEW_HTML = `<!DOCTYPE html>
<html dir="${isRtl ? 'rtl' : 'ltr'}"><head><meta charset="utf-8">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;height:100vh;overflow:hidden;position:relative}
body{background:var(--p-bg);color:var(--p-text)}
.site{padding:16px;opacity:.25}
.site .bar{height:20px;border-radius:3px;margin-bottom:12px;background:var(--p-border)}
.site .block{height:32px;border-radius:3px;margin-bottom:6px;background:var(--p-border)}
.site h1{font-size:12px;margin-bottom:6px;color:var(--p-text)}
.site p{font-size:10px;line-height:1.4;color:var(--p-muted)}

.fab{position:fixed;bottom:12px;display:inline-flex;align-items:center;gap:5px;height:38px;padding:0 14px;border:none;border-radius:19px;font-size:12px;font-weight:500;cursor:pointer;box-shadow:0 3px 14px rgba(0,0,0,.2);font-family:inherit;z-index:10;white-space:nowrap}
.fab svg{width:18px;height:18px;flex-shrink:0}

.panel{position:fixed;bottom:56px;width:260px;max-height:320px;border-radius:8px;overflow:hidden;display:flex;flex-direction:column;font-family:inherit;z-index:9;transition:opacity .2s;background:var(--p-card);color:var(--p-text);box-shadow:var(--p-shadow)}
.panel-header{padding:12px 14px;color:#fff}
.panel-header h2{font-size:13px;font-weight:600}
.panel-header p{font-size:10px;opacity:.85;margin-top:2px}
.panel-body{padding:12px 14px;flex:1;overflow-y:auto}
.panel-body .greeting{font-size:11px;margin-bottom:8px;color:var(--p-muted)}
.cta-btn{display:flex;align-items:center;gap:6px;width:100%;padding:8px 10px;border-radius:6px;font-size:11px;font-weight:500;cursor:pointer;font-family:inherit;text-align:start;border:1px solid var(--p-border);background:var(--p-card);color:var(--p-text)}
.cta-btn svg{width:16px;height:16px;flex-shrink:0}
.team-row{display:flex;align-items:center;gap:5px;padding-top:8px;margin-top:8px;border-top:1px solid var(--p-border)}
.avatar{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:600;color:#fff;overflow:hidden}
.avatar+.avatar{margin-inline-start:-5px}
.avatar img{width:100%;height:100%;object-fit:cover}
.team-label{font-size:9px;color:var(--p-muted)}
.nav{display:flex;flex-shrink:0;border-top:1px solid var(--p-border)}
.nav-tab{flex:1;display:flex;flex-direction:column;align-items:center;gap:1px;padding:6px 2px;border:none;background:none;font-family:inherit;font-size:8px;cursor:pointer;color:var(--p-muted)}
.nav-tab.active{color:var(--accent)}
.nav-tab svg{width:14px;height:14px}
</style></head>
<body>
<div class="site">
  <div class="bar"></div>
  <h1>{__('Your Website', 'wp-sms')}</h1>
  <p>{__('Preview of the messaging button widget', 'wp-sms')}</p>
  <div class="block" style="margin-top:12px"></div>
  <div class="block"></div>
  <div class="block" style="width:60%"></div>
</div>
<div id="root"></div>
<script>
var msgIcon='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>';
var homeIcon='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>';
var usersIcon='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>';
var helpIcon='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>';

function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}

/* Parse hex to [r,g,b] 0-255 */
function hexRgb(hex){return[parseInt(hex.slice(1,3),16),parseInt(hex.slice(3,5),16),parseInt(hex.slice(5,7),16)]}
/* Mix two [r,g,b] arrays: amount 0=a, 1=b */
function mix(a,b,t){return'#'+a.map(function(v,i){return Math.round(v+(b[i]-v)*t).toString(16).padStart(2,'0')}).join('')}

function setThemeVars(accent,theme){
  var s=document.body.style;
  var ac=hexRgb(accent);
  s.setProperty('--accent',accent);
  if(theme==='dark'){
    s.setProperty('--p-bg',mix(ac,[15,23,42],.85));
    s.setProperty('--p-card',mix(ac,[30,41,59],.88));
    s.setProperty('--p-text','#e2e8f0');
    s.setProperty('--p-muted','#94a3b8');
    s.setProperty('--p-border',mix(ac,[51,65,85],.82));
    s.setProperty('--p-shadow','0 4px 30px rgba(0,0,0,.3)');
  }else{
    s.setProperty('--p-bg',mix(ac,[248,250,252],.92));
    s.setProperty('--p-card','#ffffff');
    s.setProperty('--p-text','#1a1a2e');
    s.setProperty('--p-muted','#64748b');
    s.setProperty('--p-border',mix(ac,[226,232,240],.85));
    s.setProperty('--p-shadow','0 4px 30px rgba(0,0,0,.15)');
  }
}

function render(cfg){
  var c=cfg||{};
  var btn=c.button||{};
  var w=c.widget||{};
  var pages=c.pages||{};
  var team=c.team_members||[];
  var accent=btn.primary_color||'#2563eb';
  var textColor=btn.text_color||'#fff';
  var pos=btn.position==='bottom-left'?'left':'right';
  var theme=w.theme||'light';

  // Validate color values
  if(!/^#[0-9a-fA-F]{6}$/.test(accent))accent='#2563eb';
  if(!/^#[0-9a-fA-F]{6}$/.test(textColor))textColor='#ffffff';

  if(theme==='system'){theme=window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light'}
  setThemeVars(accent,theme);

  var root=document.getElementById('root');
  var showIcon=btn.style!=='text';
  var showText=btn.style!=='icon';

  var h='';
  // FAB
  h+='<button class="fab" style="'+pos+':12px;background:'+accent+';color:'+textColor+'">';
  if(showIcon)h+=msgIcon;
  if(showText)h+='<span>'+esc(btn.text||'Chat with us')+'</span>';
  h+='</button>';

  // Panel
  h+='<div class="panel" style="'+pos+':12px">';
  h+='<div class="panel-header" style="background:'+accent+'">';
  h+='<h2>'+esc(w.title||'Hi there!')+'</h2>';
  h+='<p>'+esc(w.subtitle||'How can we help?')+'</p>';
  h+='</div>';
  h+='<div class="panel-body">';
  h+='<div class="greeting">'+esc(pages.welcome?.greeting||'Welcome!')+'</div>';
  if(pages.contact_form?.enabled!==false){
    h+='<button class="cta-btn">'+msgIcon+'<span>'+esc(pages.welcome?.cta_label||'Send a message')+'</span></button>';
  }
  if(team.length>0){
    h+='<div class="team-row">';
    team.slice(0,3).forEach(function(m,i){
      h+='<div class="avatar" style="background:'+accent+'">';
      if(m.avatar_url)h+='<img src="'+esc(m.avatar_url)+'" alt="">';
      else h+=esc((m.name||'?')[0].toUpperCase());
      h+='</div>';
    });
    h+='<span class="team-label">{__('Meet our team', 'wp-sms')}</span>';
    h+='</div>';
  }
  h+='</div>';

  // Nav
  var navItems=[];
  if(pages.welcome?.enabled!==false)navItems.push({icon:homeIcon,label:'Home'});
  if(pages.contact_form?.enabled!==false)navItems.push({icon:msgIcon,label:'Message'});
  if(pages.team?.enabled!==false&&team.length>0)navItems.push({icon:usersIcon,label:'Team'});
  if(pages.resources?.enabled)navItems.push({icon:helpIcon,label:'Help'});
  if(navItems.length>1){
    h+='<div class="nav">';
    navItems.forEach(function(n,i){
      h+='<div class="nav-tab'+(i===0?' active':'')+'">'+n.icon+'<span>'+n.label+'</span></div>';
    });
    h+='</div>';
  }
  h+='</div>';
  root.innerHTML=h;
}

window.addEventListener('message',function(e){
  if(e.data&&e.data.type==='wsms-mb-preview-config'){render(e.data.config)}
});
render({});
<\/script>
</body></html>`;

export function WidgetPreview({ settings }: WidgetPreviewProps) {
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const settingsRef = useRef(settings);
  settingsRef.current = settings;

  // Attach the load listener once — it reads the latest settings via ref
  // so the listener is never detached/reattached on every keystroke.
  useEffect(() => {
    const iframe = iframeRef.current;
    if (!iframe) return;

    function sendLatest(): void {
      if (!iframe?.contentWindow) return;
      iframe.contentWindow.postMessage(
        { type: 'wsms-mb-preview-config', config: settingsRef.current },
        '*',
      );
    }

    iframe.addEventListener('load', sendLatest);
    sendLatest();

    return () => iframe.removeEventListener('load', sendLatest);
  }, []);

  // Push config updates via postMessage without touching the load listener
  useEffect(() => {
    const iframe = iframeRef.current;
    if (!iframe?.contentWindow) return;
    iframe.contentWindow.postMessage(
      { type: 'wsms-mb-preview-config', config: settings },
      '*',
    );
  }, [settings]);

  return (
    <Card>
      <CardHeader className="pb-3">
        <CardTitle className="flex items-center gap-2 text-sm">
          <Monitor className="h-4 w-4 text-muted-foreground" />
          {__('Live Preview', 'wp-sms')}
        </CardTitle>
      </CardHeader>
      <CardContent className="p-0">
        <div className="relative overflow-hidden rounded-b-lg border-t" style={{ height: 440 }}>
          <iframe
            ref={iframeRef}
            srcDoc={PREVIEW_HTML}
            className="h-full w-full border-0"
            title={__('Widget Preview', 'wp-sms')}
            sandbox="allow-scripts"
          />
        </div>
      </CardContent>
    </Card>
  );
}
