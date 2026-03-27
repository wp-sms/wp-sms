/** Compute "nice" tick values for a linear axis. When `integers` is true, step is at least 1. */
export function niceLinearTicks(min: number, max: number, targetCount = 5, integers = false): number[] {
  if (max <= min) return [0];

  const range = max - min;
  const rough = range / targetCount;
  const mag = Math.pow(10, Math.floor(Math.log10(rough)));
  const residual = rough / mag;

  let step: number;
  if (residual <= 1.5) step = mag;
  else if (residual <= 3) step = 2 * mag;
  else if (residual <= 7) step = 5 * mag;
  else step = 10 * mag;

  if (integers && step < 1) step = 1;

  const niceMin = Math.floor(min / step) * step;
  const niceMax = Math.ceil(max / step) * step;

  const ticks: number[] = [];
  for (let v = niceMin; v <= niceMax; v += step) {
    ticks.push(Math.round(v * 1e10) / 1e10); // avoid fp drift
  }
  return ticks;
}

/** Map a domain value to pixel position. */
export function linearScale(domain: [number, number], range: [number, number]) {
  const [d0, d1] = domain;
  const [r0, r1] = range;
  const span = d1 - d0 || 1;
  return (v: number) => r0 + ((v - d0) / span) * (r1 - r0);
}

/** Band scale: evenly spaced bands with configurable inner padding ratio. */
export function bandScale(labels: string[], range: [number, number], paddingInner = 0.2) {
  const [r0, r1] = range;
  const n = labels.length || 1;
  const totalWidth = r1 - r0;
  const step = totalWidth / (n + paddingInner * (n - 1) / (1 + paddingInner));
  const bandwidth = step / (1 + paddingInner);
  const gap = step - bandwidth;

  const map = new Map<string, number>();
  labels.forEach((l, i) => map.set(l, r0 + i * (bandwidth + gap)));

  return {
    bandwidth,
    get: (label: string) => map.get(label) ?? r0,
  };
}
