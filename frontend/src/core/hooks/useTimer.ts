"use client";

import { useState, useEffect, useRef } from "react";

interface UseTimerOptions {
  initialSeconds?: number;
  autoStart?: boolean;
  onTimeDone?: VoidFunction;
}

interface TimeObject {
  days: string;
  hours: string;
  minutes: string;
  seconds: string;
}

interface UseTimerReturn {
  start: () => void;
  reset: () => void;
  restart: () => void;
  isTimeDone: boolean;
  remainingTime: TimeObject;
}

const secondsToTimeObject = (totalSeconds: number): TimeObject => {
  const days = Math.floor(totalSeconds / 86400);
  const hours = Math.floor((totalSeconds % 86400) / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  return {
    days: days.toString().padStart(2, "0"),
    hours: hours.toString().padStart(2, "0"),
    minutes: minutes.toString().padStart(2, "0"),
    seconds: seconds.toString().padStart(2, "0"),
  };
};

export const useTimer = ({
  initialSeconds = 180,
  autoStart = true,
  onTimeDone,
}: UseTimerOptions = {}): UseTimerReturn => {
  const [remainingSeconds, setRemainingSeconds] = useState(initialSeconds);
  const [isTimeDone, setIsTimeDone] = useState(false);
  const intervalRef = useRef<NodeJS.Timeout | null>(null);

  const start = () => {
    if (intervalRef.current) return;

    setIsTimeDone(false);
    intervalRef.current = setInterval(() => {
      setRemainingSeconds((prev) => {
        if (prev <= 1) {
          clearInterval(intervalRef.current!);
          intervalRef.current = null;
          setIsTimeDone(true);
          onTimeDone?.();
          return 0;
        }
        return prev - 1;
      });
    }, 1000);
  };

  const reset = () => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }
    setRemainingSeconds(initialSeconds);
    setIsTimeDone(false);
  };

  useEffect(() => {
    if (autoStart) start();
  }, [autoStart]);

  const restart = () => {
    reset();
    start();
  };

  useEffect(() => {
    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, []);

  return {
    start,
    reset,
    restart,
    isTimeDone,
    remainingTime: secondsToTimeObject(remainingSeconds),
  };
};
