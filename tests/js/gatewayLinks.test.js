import { appendUtm, displayHost } from '@/lib/utils'

describe('appendUtm', () => {
  const utm = { source: 'wsms', medium: 'plugin', campaign: 'gateway-settings' }

  test('keeps a referral query string that is already on the gateway website', () => {
    const url = appendUtm('https://clicksend.com/?u=579947', utm)
    const params = new URL(url).searchParams
    expect(params.get('u')).toBe('579947')
    expect(params.get('utm_source')).toBe('wsms')
    expect(params.get('utm_medium')).toBe('plugin')
    expect(params.get('utm_campaign')).toBe('gateway-settings')
    expect(url).not.toContain('??')
  })

  test('keeps every existing parameter, not only the first', () => {
    const params = new URL(appendUtm('https://newuser.4jawaly.com/?s=1&m=202301112941417', utm)).searchParams
    expect(params.get('s')).toBe('1')
    expect(params.get('m')).toBe('202301112941417')
    expect(params.get('utm_campaign')).toBe('gateway-settings')
  })

  test('adds the parameters to a plain website', () => {
    expect(appendUtm('https://twilio.com', utm)).toBe(
      'https://twilio.com/?utm_source=wsms&utm_medium=plugin&utm_campaign=gateway-settings'
    )
  })

  test('returns the input when it is not an absolute URL', () => {
    expect(appendUtm('not a url', utm)).toBe('not a url')
    expect(appendUtm('', utm)).toBe('')
    expect(appendUtm(undefined, utm)).toBe('')
  })
})

describe('displayHost', () => {
  test('shows only the host, never the referral code', () => {
    expect(displayHost('https://clicksend.com/?u=579947')).toBe('clicksend.com')
    expect(displayHost('https://newuser.4jawaly.com/?s=1&m=202301112941417')).toBe('newuser.4jawaly.com')
  })

  test('drops scheme, www and trailing slash like the old inline code did', () => {
    expect(displayHost('https://www.twilio.com/')).toBe('twilio.com')
    expect(displayHost('http://www.springedge.com/')).toBe('springedge.com')
  })

  test('is empty for an empty value', () => {
    expect(displayHost('')).toBe('')
    expect(displayHost(undefined)).toBe('')
  })
})
