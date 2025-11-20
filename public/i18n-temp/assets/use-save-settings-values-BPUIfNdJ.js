const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["./checkbox-field-C7DnLjl-.js","./main-D1KP0B5-.js","./main-B4pHtbqT.css","./index-W778R1kW.js","./index-Cit5KdOq.js","./check-CTl8VTxS.js","./field-wrapper-BuuNVHwJ.js","./alert-DiJsRLFO.js","./sprintf-DmNrJSYG.js","./color-field-BKwHJvXz.js","./input-DTxI4jvI.js","./display-fields-YdWYTNhr.js","./separator-BSI9ZTwI.js","./image-field-Bqxum0mJ.js","./multiselect-field-CpVm0hZz.js","./popover-L9ibBOwi.js","./index-DNbzpgrM.js","./x-B6KD4r7q.js","./to-options-DMnkbffj.js","./number-field-D5-9jmzd.js","./repeater-field-ATMtFajw.js","./select-field-8ejDK4NW.js","./tel-field-DF15XWvu.js","./text-field-Dc3Q4jKb.js","./textarea-field-CdfmBeFQ.js","./password-field-BwXNaEmX.js"])))=>i.map(i=>d[i]);
import { h as Subscribable, t as pendingThenable, v as resolveEnabled, s as shallowEqualObjects, w as resolveStaleTime, l as noop, p as isServer, x as isValidTimeout, y as timeUntilStale, z as timeoutManager, A as focusManager, C as fetchState, D as replaceData, n as notifyManager, E as hashKey, F as getDefaultState, r as reactExports, G as shouldThrowError, k as useQueryClient, H as useRouter, c as createLucideIcon, j as jsxRuntimeExports, _ as __, e as cn, I as withSelectorExports, B as Button, J as __vitePreload, f as Slot, g as cva, K as Crown, S as Settings, u as useComposedRefs, M as buttonVariants, m as getSchemaByGroup, o as getSettingsValuesByGroup, N as toast, Q as instance } from "./main-D1KP0B5-.js";
import { A as Alert, C as CircleAlert, a as AlertDescription } from "./alert-DiJsRLFO.js";
import { s as sprintf } from "./sprintf-DmNrJSYG.js";
import { i as createDialogScope, R as Root, W as WarningProvider, c as createContextScope, C as Content, b as composeEventHandlers, h as createSlottable, T as Title, D as Description, f as Close, O as Overlay, j as Trigger } from "./index-W778R1kW.js";
var QueryObserver = class extends Subscribable {
  constructor(client, options) {
    super();
    this.options = options;
    this.#client = client;
    this.#selectError = null;
    this.#currentThenable = pendingThenable();
    this.bindMethods();
    this.setOptions(options);
  }
  #client;
  #currentQuery = void 0;
  #currentQueryInitialState = void 0;
  #currentResult = void 0;
  #currentResultState;
  #currentResultOptions;
  #currentThenable;
  #selectError;
  #selectFn;
  #selectResult;
  // This property keeps track of the last query with defined data.
  // It will be used to pass the previous data and query to the placeholder function between renders.
  #lastQueryWithDefinedData;
  #staleTimeoutId;
  #refetchIntervalId;
  #currentRefetchInterval;
  #trackedProps = /* @__PURE__ */ new Set();
  bindMethods() {
    this.refetch = this.refetch.bind(this);
  }
  onSubscribe() {
    if (this.listeners.size === 1) {
      this.#currentQuery.addObserver(this);
      if (shouldFetchOnMount(this.#currentQuery, this.options)) {
        this.#executeFetch();
      } else {
        this.updateResult();
      }
      this.#updateTimers();
    }
  }
  onUnsubscribe() {
    if (!this.hasListeners()) {
      this.destroy();
    }
  }
  shouldFetchOnReconnect() {
    return shouldFetchOn(
      this.#currentQuery,
      this.options,
      this.options.refetchOnReconnect
    );
  }
  shouldFetchOnWindowFocus() {
    return shouldFetchOn(
      this.#currentQuery,
      this.options,
      this.options.refetchOnWindowFocus
    );
  }
  destroy() {
    this.listeners = /* @__PURE__ */ new Set();
    this.#clearStaleTimeout();
    this.#clearRefetchInterval();
    this.#currentQuery.removeObserver(this);
  }
  setOptions(options) {
    const prevOptions = this.options;
    const prevQuery = this.#currentQuery;
    this.options = this.#client.defaultQueryOptions(options);
    if (this.options.enabled !== void 0 && typeof this.options.enabled !== "boolean" && typeof this.options.enabled !== "function" && typeof resolveEnabled(this.options.enabled, this.#currentQuery) !== "boolean") {
      throw new Error(
        "Expected enabled to be a boolean or a callback that returns a boolean"
      );
    }
    this.#updateQuery();
    this.#currentQuery.setOptions(this.options);
    if (prevOptions._defaulted && !shallowEqualObjects(this.options, prevOptions)) {
      this.#client.getQueryCache().notify({
        type: "observerOptionsUpdated",
        query: this.#currentQuery,
        observer: this
      });
    }
    const mounted = this.hasListeners();
    if (mounted && shouldFetchOptionally(
      this.#currentQuery,
      prevQuery,
      this.options,
      prevOptions
    )) {
      this.#executeFetch();
    }
    this.updateResult();
    if (mounted && (this.#currentQuery !== prevQuery || resolveEnabled(this.options.enabled, this.#currentQuery) !== resolveEnabled(prevOptions.enabled, this.#currentQuery) || resolveStaleTime(this.options.staleTime, this.#currentQuery) !== resolveStaleTime(prevOptions.staleTime, this.#currentQuery))) {
      this.#updateStaleTimeout();
    }
    const nextRefetchInterval = this.#computeRefetchInterval();
    if (mounted && (this.#currentQuery !== prevQuery || resolveEnabled(this.options.enabled, this.#currentQuery) !== resolveEnabled(prevOptions.enabled, this.#currentQuery) || nextRefetchInterval !== this.#currentRefetchInterval)) {
      this.#updateRefetchInterval(nextRefetchInterval);
    }
  }
  getOptimisticResult(options) {
    const query = this.#client.getQueryCache().build(this.#client, options);
    const result = this.createResult(query, options);
    if (shouldAssignObserverCurrentProperties(this, result)) {
      this.#currentResult = result;
      this.#currentResultOptions = this.options;
      this.#currentResultState = this.#currentQuery.state;
    }
    return result;
  }
  getCurrentResult() {
    return this.#currentResult;
  }
  trackResult(result, onPropTracked) {
    return new Proxy(result, {
      get: (target, key) => {
        this.trackProp(key);
        onPropTracked?.(key);
        if (key === "promise") {
          this.trackProp("data");
          if (!this.options.experimental_prefetchInRender && this.#currentThenable.status === "pending") {
            this.#currentThenable.reject(
              new Error(
                "experimental_prefetchInRender feature flag is not enabled"
              )
            );
          }
        }
        return Reflect.get(target, key);
      }
    });
  }
  trackProp(key) {
    this.#trackedProps.add(key);
  }
  getCurrentQuery() {
    return this.#currentQuery;
  }
  refetch({ ...options } = {}) {
    return this.fetch({
      ...options
    });
  }
  fetchOptimistic(options) {
    const defaultedOptions = this.#client.defaultQueryOptions(options);
    const query = this.#client.getQueryCache().build(this.#client, defaultedOptions);
    return query.fetch().then(() => this.createResult(query, defaultedOptions));
  }
  fetch(fetchOptions) {
    return this.#executeFetch({
      ...fetchOptions,
      cancelRefetch: fetchOptions.cancelRefetch ?? true
    }).then(() => {
      this.updateResult();
      return this.#currentResult;
    });
  }
  #executeFetch(fetchOptions) {
    this.#updateQuery();
    let promise = this.#currentQuery.fetch(
      this.options,
      fetchOptions
    );
    if (!fetchOptions?.throwOnError) {
      promise = promise.catch(noop);
    }
    return promise;
  }
  #updateStaleTimeout() {
    this.#clearStaleTimeout();
    const staleTime = resolveStaleTime(
      this.options.staleTime,
      this.#currentQuery
    );
    if (isServer || this.#currentResult.isStale || !isValidTimeout(staleTime)) {
      return;
    }
    const time = timeUntilStale(this.#currentResult.dataUpdatedAt, staleTime);
    const timeout = time + 1;
    this.#staleTimeoutId = timeoutManager.setTimeout(() => {
      if (!this.#currentResult.isStale) {
        this.updateResult();
      }
    }, timeout);
  }
  #computeRefetchInterval() {
    return (typeof this.options.refetchInterval === "function" ? this.options.refetchInterval(this.#currentQuery) : this.options.refetchInterval) ?? false;
  }
  #updateRefetchInterval(nextInterval) {
    this.#clearRefetchInterval();
    this.#currentRefetchInterval = nextInterval;
    if (isServer || resolveEnabled(this.options.enabled, this.#currentQuery) === false || !isValidTimeout(this.#currentRefetchInterval) || this.#currentRefetchInterval === 0) {
      return;
    }
    this.#refetchIntervalId = timeoutManager.setInterval(() => {
      if (this.options.refetchIntervalInBackground || focusManager.isFocused()) {
        this.#executeFetch();
      }
    }, this.#currentRefetchInterval);
  }
  #updateTimers() {
    this.#updateStaleTimeout();
    this.#updateRefetchInterval(this.#computeRefetchInterval());
  }
  #clearStaleTimeout() {
    if (this.#staleTimeoutId) {
      timeoutManager.clearTimeout(this.#staleTimeoutId);
      this.#staleTimeoutId = void 0;
    }
  }
  #clearRefetchInterval() {
    if (this.#refetchIntervalId) {
      timeoutManager.clearInterval(this.#refetchIntervalId);
      this.#refetchIntervalId = void 0;
    }
  }
  createResult(query, options) {
    const prevQuery = this.#currentQuery;
    const prevOptions = this.options;
    const prevResult = this.#currentResult;
    const prevResultState = this.#currentResultState;
    const prevResultOptions = this.#currentResultOptions;
    const queryChange = query !== prevQuery;
    const queryInitialState = queryChange ? query.state : this.#currentQueryInitialState;
    const { state } = query;
    let newState = { ...state };
    let isPlaceholderData = false;
    let data;
    if (options._optimisticResults) {
      const mounted = this.hasListeners();
      const fetchOnMount = !mounted && shouldFetchOnMount(query, options);
      const fetchOptionally = mounted && shouldFetchOptionally(query, prevQuery, options, prevOptions);
      if (fetchOnMount || fetchOptionally) {
        newState = {
          ...newState,
          ...fetchState(state.data, query.options)
        };
      }
      if (options._optimisticResults === "isRestoring") {
        newState.fetchStatus = "idle";
      }
    }
    let { error, errorUpdatedAt, status } = newState;
    data = newState.data;
    let skipSelect = false;
    if (options.placeholderData !== void 0 && data === void 0 && status === "pending") {
      let placeholderData;
      if (prevResult?.isPlaceholderData && options.placeholderData === prevResultOptions?.placeholderData) {
        placeholderData = prevResult.data;
        skipSelect = true;
      } else {
        placeholderData = typeof options.placeholderData === "function" ? options.placeholderData(
          this.#lastQueryWithDefinedData?.state.data,
          this.#lastQueryWithDefinedData
        ) : options.placeholderData;
      }
      if (placeholderData !== void 0) {
        status = "success";
        data = replaceData(
          prevResult?.data,
          placeholderData,
          options
        );
        isPlaceholderData = true;
      }
    }
    if (options.select && data !== void 0 && !skipSelect) {
      if (prevResult && data === prevResultState?.data && options.select === this.#selectFn) {
        data = this.#selectResult;
      } else {
        try {
          this.#selectFn = options.select;
          data = options.select(data);
          data = replaceData(prevResult?.data, data, options);
          this.#selectResult = data;
          this.#selectError = null;
        } catch (selectError) {
          this.#selectError = selectError;
        }
      }
    }
    if (this.#selectError) {
      error = this.#selectError;
      data = this.#selectResult;
      errorUpdatedAt = Date.now();
      status = "error";
    }
    const isFetching = newState.fetchStatus === "fetching";
    const isPending = status === "pending";
    const isError = status === "error";
    const isLoading = isPending && isFetching;
    const hasData = data !== void 0;
    const result = {
      status,
      fetchStatus: newState.fetchStatus,
      isPending,
      isSuccess: status === "success",
      isError,
      isInitialLoading: isLoading,
      isLoading,
      data,
      dataUpdatedAt: newState.dataUpdatedAt,
      error,
      errorUpdatedAt,
      failureCount: newState.fetchFailureCount,
      failureReason: newState.fetchFailureReason,
      errorUpdateCount: newState.errorUpdateCount,
      isFetched: newState.dataUpdateCount > 0 || newState.errorUpdateCount > 0,
      isFetchedAfterMount: newState.dataUpdateCount > queryInitialState.dataUpdateCount || newState.errorUpdateCount > queryInitialState.errorUpdateCount,
      isFetching,
      isRefetching: isFetching && !isPending,
      isLoadingError: isError && !hasData,
      isPaused: newState.fetchStatus === "paused",
      isPlaceholderData,
      isRefetchError: isError && hasData,
      isStale: isStale(query, options),
      refetch: this.refetch,
      promise: this.#currentThenable,
      isEnabled: resolveEnabled(options.enabled, query) !== false
    };
    const nextResult = result;
    if (this.options.experimental_prefetchInRender) {
      const finalizeThenableIfPossible = (thenable) => {
        if (nextResult.status === "error") {
          thenable.reject(nextResult.error);
        } else if (nextResult.data !== void 0) {
          thenable.resolve(nextResult.data);
        }
      };
      const recreateThenable = () => {
        const pending = this.#currentThenable = nextResult.promise = pendingThenable();
        finalizeThenableIfPossible(pending);
      };
      const prevThenable = this.#currentThenable;
      switch (prevThenable.status) {
        case "pending":
          if (query.queryHash === prevQuery.queryHash) {
            finalizeThenableIfPossible(prevThenable);
          }
          break;
        case "fulfilled":
          if (nextResult.status === "error" || nextResult.data !== prevThenable.value) {
            recreateThenable();
          }
          break;
        case "rejected":
          if (nextResult.status !== "error" || nextResult.error !== prevThenable.reason) {
            recreateThenable();
          }
          break;
      }
    }
    return nextResult;
  }
  updateResult() {
    const prevResult = this.#currentResult;
    const nextResult = this.createResult(this.#currentQuery, this.options);
    this.#currentResultState = this.#currentQuery.state;
    this.#currentResultOptions = this.options;
    if (this.#currentResultState.data !== void 0) {
      this.#lastQueryWithDefinedData = this.#currentQuery;
    }
    if (shallowEqualObjects(nextResult, prevResult)) {
      return;
    }
    this.#currentResult = nextResult;
    const shouldNotifyListeners = () => {
      if (!prevResult) {
        return true;
      }
      const { notifyOnChangeProps } = this.options;
      const notifyOnChangePropsValue = typeof notifyOnChangeProps === "function" ? notifyOnChangeProps() : notifyOnChangeProps;
      if (notifyOnChangePropsValue === "all" || !notifyOnChangePropsValue && !this.#trackedProps.size) {
        return true;
      }
      const includedProps = new Set(
        notifyOnChangePropsValue ?? this.#trackedProps
      );
      if (this.options.throwOnError) {
        includedProps.add("error");
      }
      return Object.keys(this.#currentResult).some((key) => {
        const typedKey = key;
        const changed = this.#currentResult[typedKey] !== prevResult[typedKey];
        return changed && includedProps.has(typedKey);
      });
    };
    this.#notify({ listeners: shouldNotifyListeners() });
  }
  #updateQuery() {
    const query = this.#client.getQueryCache().build(this.#client, this.options);
    if (query === this.#currentQuery) {
      return;
    }
    const prevQuery = this.#currentQuery;
    this.#currentQuery = query;
    this.#currentQueryInitialState = query.state;
    if (this.hasListeners()) {
      prevQuery?.removeObserver(this);
      query.addObserver(this);
    }
  }
  onQueryUpdate() {
    this.updateResult();
    if (this.hasListeners()) {
      this.#updateTimers();
    }
  }
  #notify(notifyOptions) {
    notifyManager.batch(() => {
      if (notifyOptions.listeners) {
        this.listeners.forEach((listener) => {
          listener(this.#currentResult);
        });
      }
      this.#client.getQueryCache().notify({
        query: this.#currentQuery,
        type: "observerResultsUpdated"
      });
    });
  }
};
function shouldLoadOnMount(query, options) {
  return resolveEnabled(options.enabled, query) !== false && query.state.data === void 0 && !(query.state.status === "error" && options.retryOnMount === false);
}
function shouldFetchOnMount(query, options) {
  return shouldLoadOnMount(query, options) || query.state.data !== void 0 && shouldFetchOn(query, options, options.refetchOnMount);
}
function shouldFetchOn(query, options, field) {
  if (resolveEnabled(options.enabled, query) !== false && resolveStaleTime(options.staleTime, query) !== "static") {
    const value = typeof field === "function" ? field(query) : field;
    return value === "always" || value !== false && isStale(query, options);
  }
  return false;
}
function shouldFetchOptionally(query, prevQuery, options, prevOptions) {
  return (query !== prevQuery || resolveEnabled(prevOptions.enabled, query) === false) && (!options.suspense || query.state.status !== "error") && isStale(query, options);
}
function isStale(query, options) {
  return resolveEnabled(options.enabled, query) !== false && query.isStaleByTime(resolveStaleTime(options.staleTime, query));
}
function shouldAssignObserverCurrentProperties(observer, optimisticResult) {
  if (!shallowEqualObjects(observer.getCurrentResult(), optimisticResult)) {
    return true;
  }
  return false;
}
var MutationObserver = class extends Subscribable {
  #client;
  #currentResult = void 0;
  #currentMutation;
  #mutateOptions;
  constructor(client, options) {
    super();
    this.#client = client;
    this.setOptions(options);
    this.bindMethods();
    this.#updateResult();
  }
  bindMethods() {
    this.mutate = this.mutate.bind(this);
    this.reset = this.reset.bind(this);
  }
  setOptions(options) {
    const prevOptions = this.options;
    this.options = this.#client.defaultMutationOptions(options);
    if (!shallowEqualObjects(this.options, prevOptions)) {
      this.#client.getMutationCache().notify({
        type: "observerOptionsUpdated",
        mutation: this.#currentMutation,
        observer: this
      });
    }
    if (prevOptions?.mutationKey && this.options.mutationKey && hashKey(prevOptions.mutationKey) !== hashKey(this.options.mutationKey)) {
      this.reset();
    } else if (this.#currentMutation?.state.status === "pending") {
      this.#currentMutation.setOptions(this.options);
    }
  }
  onUnsubscribe() {
    if (!this.hasListeners()) {
      this.#currentMutation?.removeObserver(this);
    }
  }
  onMutationUpdate(action) {
    this.#updateResult();
    this.#notify(action);
  }
  getCurrentResult() {
    return this.#currentResult;
  }
  reset() {
    this.#currentMutation?.removeObserver(this);
    this.#currentMutation = void 0;
    this.#updateResult();
    this.#notify();
  }
  mutate(variables, options) {
    this.#mutateOptions = options;
    this.#currentMutation?.removeObserver(this);
    this.#currentMutation = this.#client.getMutationCache().build(this.#client, this.options);
    this.#currentMutation.addObserver(this);
    return this.#currentMutation.execute(variables);
  }
  #updateResult() {
    const state = this.#currentMutation?.state ?? getDefaultState();
    this.#currentResult = {
      ...state,
      isPending: state.status === "pending",
      isSuccess: state.status === "success",
      isError: state.status === "error",
      isIdle: state.status === "idle",
      mutate: this.mutate,
      reset: this.reset
    };
  }
  #notify(action) {
    notifyManager.batch(() => {
      if (this.#mutateOptions && this.hasListeners()) {
        const variables = this.#currentResult.variables;
        const onMutateResult = this.#currentResult.context;
        const context = {
          client: this.#client,
          meta: this.options.meta,
          mutationKey: this.options.mutationKey
        };
        if (action?.type === "success") {
          this.#mutateOptions.onSuccess?.(
            action.data,
            variables,
            onMutateResult,
            context
          );
          this.#mutateOptions.onSettled?.(
            action.data,
            null,
            variables,
            onMutateResult,
            context
          );
        } else if (action?.type === "error") {
          this.#mutateOptions.onError?.(
            action.error,
            variables,
            onMutateResult,
            context
          );
          this.#mutateOptions.onSettled?.(
            void 0,
            action.error,
            variables,
            onMutateResult,
            context
          );
        }
      }
      this.listeners.forEach((listener) => {
        listener(this.#currentResult);
      });
    });
  }
};
var IsRestoringContext = reactExports.createContext(false);
var useIsRestoring = () => reactExports.useContext(IsRestoringContext);
IsRestoringContext.Provider;
function createValue() {
  let isReset = false;
  return {
    clearReset: () => {
      isReset = false;
    },
    reset: () => {
      isReset = true;
    },
    isReset: () => {
      return isReset;
    }
  };
}
var QueryErrorResetBoundaryContext = reactExports.createContext(createValue());
var useQueryErrorResetBoundary = () => reactExports.useContext(QueryErrorResetBoundaryContext);
var ensurePreventErrorBoundaryRetry = (options, errorResetBoundary) => {
  if (options.suspense || options.throwOnError || options.experimental_prefetchInRender) {
    if (!errorResetBoundary.isReset()) {
      options.retryOnMount = false;
    }
  }
};
var useClearResetErrorBoundary = (errorResetBoundary) => {
  reactExports.useEffect(() => {
    errorResetBoundary.clearReset();
  }, [errorResetBoundary]);
};
var getHasError = ({
  result,
  errorResetBoundary,
  throwOnError,
  query,
  suspense
}) => {
  return result.isError && !errorResetBoundary.isReset() && !result.isFetching && query && (suspense && result.data === void 0 || shouldThrowError(throwOnError, [result.error, query]));
};
var defaultThrowOnError = (_error, query) => query.state.data === void 0;
var ensureSuspenseTimers = (defaultedOptions) => {
  if (defaultedOptions.suspense) {
    const MIN_SUSPENSE_TIME_MS = 1e3;
    const clamp = (value) => value === "static" ? value : Math.max(value ?? MIN_SUSPENSE_TIME_MS, MIN_SUSPENSE_TIME_MS);
    const originalStaleTime = defaultedOptions.staleTime;
    defaultedOptions.staleTime = typeof originalStaleTime === "function" ? (...args) => clamp(originalStaleTime(...args)) : clamp(originalStaleTime);
    if (typeof defaultedOptions.gcTime === "number") {
      defaultedOptions.gcTime = Math.max(
        defaultedOptions.gcTime,
        MIN_SUSPENSE_TIME_MS
      );
    }
  }
};
var willFetch = (result, isRestoring) => result.isLoading && result.isFetching && !isRestoring;
var shouldSuspend = (defaultedOptions, result) => defaultedOptions?.suspense && result.isPending;
var fetchOptimistic = (defaultedOptions, observer, errorResetBoundary) => observer.fetchOptimistic(defaultedOptions).catch(() => {
  errorResetBoundary.clearReset();
});
function useMutation(options, queryClient) {
  const client = useQueryClient();
  const [observer] = reactExports.useState(
    () => new MutationObserver(
      client,
      options
    )
  );
  reactExports.useEffect(() => {
    observer.setOptions(options);
  }, [observer, options]);
  const result = reactExports.useSyncExternalStore(
    reactExports.useCallback(
      (onStoreChange) => observer.subscribe(notifyManager.batchCalls(onStoreChange)),
      [observer]
    ),
    () => observer.getCurrentResult(),
    () => observer.getCurrentResult()
  );
  const mutate = reactExports.useCallback(
    (variables, mutateOptions) => {
      observer.mutate(variables, mutateOptions).catch(noop);
    },
    [observer]
  );
  if (result.error && shouldThrowError(observer.options.throwOnError, [result.error])) {
    throw result.error;
  }
  return { ...result, mutate, mutateAsync: result.mutate };
}
function _resolveBlockerOpts(opts, condition) {
  if (opts === void 0) {
    return {
      shouldBlockFn: () => true,
      withResolver: false
    };
  }
  if ("shouldBlockFn" in opts) {
    return opts;
  }
  if (typeof opts === "function") {
    const shouldBlock2 = Boolean(true);
    const _customBlockerFn2 = async () => {
      if (shouldBlock2) return await opts();
      return false;
    };
    return {
      shouldBlockFn: _customBlockerFn2,
      enableBeforeUnload: shouldBlock2,
      withResolver: false
    };
  }
  const shouldBlock = Boolean(opts.condition ?? true);
  const fn = opts.blockerFn;
  const _customBlockerFn = async () => {
    if (shouldBlock && fn !== void 0) {
      return await fn();
    }
    return shouldBlock;
  };
  return {
    shouldBlockFn: _customBlockerFn,
    enableBeforeUnload: shouldBlock,
    withResolver: fn === void 0
  };
}
function useBlocker(opts, condition) {
  const {
    shouldBlockFn,
    enableBeforeUnload = true,
    disabled = false,
    withResolver = false
  } = _resolveBlockerOpts(opts);
  const router = useRouter();
  const { history } = router;
  const [resolver, setResolver] = reactExports.useState({
    status: "idle",
    current: void 0,
    next: void 0,
    action: void 0,
    proceed: void 0,
    reset: void 0
  });
  reactExports.useEffect(() => {
    const blockerFnComposed = async (blockerFnArgs) => {
      function getLocation(location) {
        const parsedLocation = router.parseLocation(location);
        const matchedRoutes = router.getMatchedRoutes(parsedLocation.pathname);
        if (matchedRoutes.foundRoute === void 0) {
          throw new Error(`No route found for location ${location.href}`);
        }
        return {
          routeId: matchedRoutes.foundRoute.id,
          fullPath: matchedRoutes.foundRoute.fullPath,
          pathname: parsedLocation.pathname,
          params: matchedRoutes.routeParams,
          search: parsedLocation.search
        };
      }
      const current = getLocation(blockerFnArgs.currentLocation);
      const next = getLocation(blockerFnArgs.nextLocation);
      const shouldBlock = await shouldBlockFn({
        action: blockerFnArgs.action,
        current,
        next
      });
      if (!withResolver) {
        return shouldBlock;
      }
      if (!shouldBlock) {
        return false;
      }
      const promise = new Promise((resolve) => {
        setResolver({
          status: "blocked",
          current,
          next,
          action: blockerFnArgs.action,
          proceed: () => resolve(false),
          reset: () => resolve(true)
        });
      });
      const canNavigateAsync = await promise;
      setResolver({
        status: "idle",
        current: void 0,
        next: void 0,
        action: void 0,
        proceed: void 0,
        reset: void 0
      });
      return canNavigateAsync;
    };
    return disabled ? void 0 : history.block({ blockerFn: blockerFnComposed, enableBeforeUnload });
  }, [
    shouldBlockFn,
    enableBeforeUnload,
    disabled,
    withResolver,
    history,
    router
  ]);
  return resolver;
}
const __iconNode$5 = [
  ["path", { d: "M4.5 3h15", key: "c7n0jr" }],
  ["path", { d: "M6 3v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3", key: "m1uhx7" }],
  ["path", { d: "M6 14h12", key: "4cwo0f" }]
];
const Beaker = createLucideIcon("beaker", __iconNode$5);
const __iconNode$4 = [
  ["path", { d: "M12 6v6l4 2", key: "mmk7yg" }],
  ["circle", { cx: "12", cy: "12", r: "10", key: "1mglay" }]
];
const Clock = createLucideIcon("clock", __iconNode$4);
const __iconNode$3 = [
  [
    "path",
    {
      d: "M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z",
      key: "1c8476"
    }
  ],
  ["path", { d: "M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7", key: "1ydtos" }],
  ["path", { d: "M7 3v4a1 1 0 0 0 1 1h7", key: "t51u73" }]
];
const Save = createLucideIcon("save", __iconNode$3);
const __iconNode$2 = [
  [
    "path",
    {
      d: "M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z",
      key: "4pj2yx"
    }
  ],
  ["path", { d: "M20 3v4", key: "1olli1" }],
  ["path", { d: "M22 5h-4", key: "1gvqau" }],
  ["path", { d: "M4 17v2", key: "vumght" }],
  ["path", { d: "M5 18H3", key: "zchphs" }]
];
const Sparkles = createLucideIcon("sparkles", __iconNode$2);
const __iconNode$1 = [
  ["path", { d: "M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2", key: "125lnx" }],
  ["path", { d: "M8.5 2h7", key: "csnxdl" }],
  ["path", { d: "M14.5 16h-5", key: "1ox875" }]
];
const TestTube = createLucideIcon("test-tube", __iconNode$1);
const __iconNode = [
  [
    "path",
    {
      d: "m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3",
      key: "wmoenq"
    }
  ],
  ["path", { d: "M12 9v4", key: "juzpu7" }],
  ["path", { d: "M12 17h.01", key: "p32p05" }]
];
const TriangleAlert = createLucideIcon("triangle-alert", __iconNode);
const GroupTitle = ({ label }) => {
  return /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex flex-col gap-1", children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx("h1", { className: "text-2xl font-bold tracking-tight", children: label }),
    /* @__PURE__ */ jsxRuntimeExports.jsx("p", { className: "text-base text-muted-foreground", children: __("Configure your settings and preferences", "wp-sms") })
  ] });
};
function Card({ className, ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(
    "div",
    {
      "data-slot": "card",
      className: cn(
        "flex flex-col gap-6 py-6 space-y-1",
        "rounded-lg border bg-card text-card-foreground shadow-sm",
        className
      ),
      ...props
    }
  );
}
function CardHeader({ className, ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(
    "div",
    {
      "data-slot": "card-header",
      className: cn(
        "@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1 px-3 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6",
        className
      ),
      ...props
    }
  );
}
function CardTitle({ className, ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(
    "div",
    {
      "data-slot": "card-title",
      className: cn("leading-none font-semibold text-lg tracking-tight", className),
      ...props
    }
  );
}
function CardDescription({ className, ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx("div", { "data-slot": "card-description", className: cn("text-muted-foreground text-sm", className), ...props });
}
function CardContent({ className, ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx("div", { "data-slot": "card-content", className: cn("px-6", className), ...props });
}
const __storeToDerived = /* @__PURE__ */ new WeakMap();
const __derivedToStore = /* @__PURE__ */ new WeakMap();
const __depsThatHaveWrittenThisTick = {
  current: []
};
let __isFlushing = false;
let __batchDepth = 0;
const __pendingUpdates = /* @__PURE__ */ new Set();
const __initialBatchValues = /* @__PURE__ */ new Map();
function __flush_internals(relatedVals) {
  const sorted = Array.from(relatedVals).sort((a, b) => {
    if (a instanceof Derived && a.options.deps.includes(b)) return 1;
    if (b instanceof Derived && b.options.deps.includes(a)) return -1;
    return 0;
  });
  for (const derived of sorted) {
    if (__depsThatHaveWrittenThisTick.current.includes(derived)) {
      continue;
    }
    __depsThatHaveWrittenThisTick.current.push(derived);
    derived.recompute();
    const stores = __derivedToStore.get(derived);
    if (stores) {
      for (const store of stores) {
        const relatedLinkedDerivedVals = __storeToDerived.get(store);
        if (!relatedLinkedDerivedVals) continue;
        __flush_internals(relatedLinkedDerivedVals);
      }
    }
  }
}
function __notifyListeners(store) {
  const value = {
    prevVal: store.prevState,
    currentVal: store.state
  };
  for (const listener of store.listeners) {
    listener(value);
  }
}
function __notifyDerivedListeners(derived) {
  const value = {
    prevVal: derived.prevState,
    currentVal: derived.state
  };
  for (const listener of derived.listeners) {
    listener(value);
  }
}
function __flush(store) {
  if (__batchDepth > 0 && !__initialBatchValues.has(store)) {
    __initialBatchValues.set(store, store.prevState);
  }
  __pendingUpdates.add(store);
  if (__batchDepth > 0) return;
  if (__isFlushing) return;
  try {
    __isFlushing = true;
    while (__pendingUpdates.size > 0) {
      const stores = Array.from(__pendingUpdates);
      __pendingUpdates.clear();
      for (const store2 of stores) {
        const prevState = __initialBatchValues.get(store2) ?? store2.prevState;
        store2.prevState = prevState;
        __notifyListeners(store2);
      }
      for (const store2 of stores) {
        const derivedVals = __storeToDerived.get(store2);
        if (!derivedVals) continue;
        __depsThatHaveWrittenThisTick.current.push(store2);
        __flush_internals(derivedVals);
      }
      for (const store2 of stores) {
        const derivedVals = __storeToDerived.get(store2);
        if (!derivedVals) continue;
        for (const derived of derivedVals) {
          __notifyDerivedListeners(derived);
        }
      }
    }
  } finally {
    __isFlushing = false;
    __depsThatHaveWrittenThisTick.current = [];
    __initialBatchValues.clear();
  }
}
function batch(fn) {
  __batchDepth++;
  try {
    fn();
  } finally {
    __batchDepth--;
    if (__batchDepth === 0) {
      const pendingUpdateToFlush = __pendingUpdates.values().next().value;
      if (pendingUpdateToFlush) {
        __flush(pendingUpdateToFlush);
      }
    }
  }
}
function isUpdaterFunction(updater) {
  return typeof updater === "function";
}
class Store {
  constructor(initialState, options) {
    this.listeners = /* @__PURE__ */ new Set();
    this.subscribe = (listener) => {
      var _a, _b;
      this.listeners.add(listener);
      const unsub = (_b = (_a = this.options) == null ? void 0 : _a.onSubscribe) == null ? void 0 : _b.call(_a, listener, this);
      return () => {
        this.listeners.delete(listener);
        unsub == null ? void 0 : unsub();
      };
    };
    this.prevState = initialState;
    this.state = initialState;
    this.options = options;
  }
  setState(updater) {
    var _a, _b, _c;
    this.prevState = this.state;
    if ((_a = this.options) == null ? void 0 : _a.updateFn) {
      this.state = this.options.updateFn(this.prevState)(updater);
    } else {
      if (isUpdaterFunction(updater)) {
        this.state = updater(this.prevState);
      } else {
        this.state = updater;
      }
    }
    (_c = (_b = this.options) == null ? void 0 : _b.onUpdate) == null ? void 0 : _c.call(_b);
    __flush(this);
  }
}
class Derived {
  constructor(options) {
    this.listeners = /* @__PURE__ */ new Set();
    this._subscriptions = [];
    this.lastSeenDepValues = [];
    this.getDepVals = () => {
      const l = this.options.deps.length;
      const prevDepVals = new Array(l);
      const currDepVals = new Array(l);
      for (let i = 0; i < l; i++) {
        const dep = this.options.deps[i];
        prevDepVals[i] = dep.prevState;
        currDepVals[i] = dep.state;
      }
      this.lastSeenDepValues = currDepVals;
      return {
        prevDepVals,
        currDepVals,
        prevVal: this.prevState ?? void 0
      };
    };
    this.recompute = () => {
      var _a, _b;
      this.prevState = this.state;
      const depVals = this.getDepVals();
      this.state = this.options.fn(depVals);
      (_b = (_a = this.options).onUpdate) == null ? void 0 : _b.call(_a);
    };
    this.checkIfRecalculationNeededDeeply = () => {
      for (const dep of this.options.deps) {
        if (dep instanceof Derived) {
          dep.checkIfRecalculationNeededDeeply();
        }
      }
      let shouldRecompute = false;
      const lastSeenDepValues = this.lastSeenDepValues;
      const { currDepVals } = this.getDepVals();
      for (let i = 0; i < currDepVals.length; i++) {
        if (currDepVals[i] !== lastSeenDepValues[i]) {
          shouldRecompute = true;
          break;
        }
      }
      if (shouldRecompute) {
        this.recompute();
      }
    };
    this.mount = () => {
      this.registerOnGraph();
      this.checkIfRecalculationNeededDeeply();
      return () => {
        this.unregisterFromGraph();
        for (const cleanup of this._subscriptions) {
          cleanup();
        }
      };
    };
    this.subscribe = (listener) => {
      var _a, _b;
      this.listeners.add(listener);
      const unsub = (_b = (_a = this.options).onSubscribe) == null ? void 0 : _b.call(_a, listener, this);
      return () => {
        this.listeners.delete(listener);
        unsub == null ? void 0 : unsub();
      };
    };
    this.options = options;
    this.state = options.fn({
      prevDepVals: void 0,
      prevVal: void 0,
      currDepVals: this.getDepVals().currDepVals
    });
  }
  registerOnGraph(deps = this.options.deps) {
    for (const dep of deps) {
      if (dep instanceof Derived) {
        dep.registerOnGraph();
        this.registerOnGraph(dep.options.deps);
      } else if (dep instanceof Store) {
        let relatedLinkedDerivedVals = __storeToDerived.get(dep);
        if (!relatedLinkedDerivedVals) {
          relatedLinkedDerivedVals = /* @__PURE__ */ new Set();
          __storeToDerived.set(dep, relatedLinkedDerivedVals);
        }
        relatedLinkedDerivedVals.add(this);
        let relatedStores = __derivedToStore.get(this);
        if (!relatedStores) {
          relatedStores = /* @__PURE__ */ new Set();
          __derivedToStore.set(this, relatedStores);
        }
        relatedStores.add(dep);
      }
    }
  }
  unregisterFromGraph(deps = this.options.deps) {
    for (const dep of deps) {
      if (dep instanceof Derived) {
        this.unregisterFromGraph(dep.options.deps);
      } else if (dep instanceof Store) {
        const relatedLinkedDerivedVals = __storeToDerived.get(dep);
        if (relatedLinkedDerivedVals) {
          relatedLinkedDerivedVals.delete(this);
        }
        const relatedStores = __derivedToStore.get(this);
        if (relatedStores) {
          relatedStores.delete(dep);
        }
      }
    }
  }
}
function isFunction(value) {
  return typeof value === "function";
}
function parseFunctionOrValue(value, ...args) {
  return isFunction(value) ? value(...args) : value;
}
function createKey(key) {
  if (key) {
    return key;
  }
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }
  return "";
}
class EventClient {
  #enabled = true;
  #pluginId;
  #eventTarget;
  #debug;
  #queuedEvents;
  #connected;
  #connectIntervalId;
  #connectEveryMs;
  #retryCount = 0;
  #maxRetries = 5;
  #connecting = false;
  #onConnected = () => {
    this.debugLog("Connected to event bus");
    this.#connected = true;
    this.#connecting = false;
    this.debugLog("Emitting queued events", this.#queuedEvents);
    this.#queuedEvents.forEach((event) => this.emitEventToBus(event));
    this.#queuedEvents = [];
    this.stopConnectLoop();
    this.#eventTarget().removeEventListener(
      "tanstack-connect-success",
      this.#onConnected
    );
  };
  // fired off right away and then at intervals
  #retryConnection = () => {
    if (this.#retryCount < this.#maxRetries) {
      this.#retryCount++;
      this.dispatchCustomEvent("tanstack-connect", {});
      return;
    }
    this.#eventTarget().removeEventListener(
      "tanstack-connect",
      this.#retryConnection
    );
    this.debugLog("Max retries reached, giving up on connection");
    this.stopConnectLoop();
  };
  // This is run to register connection handlers on first emit attempt
  #connectFunction = () => {
    if (this.#connecting) return;
    this.#connecting = true;
    this.#eventTarget().addEventListener(
      "tanstack-connect-success",
      this.#onConnected
    );
    this.#retryConnection();
  };
  constructor({
    pluginId,
    debug = false,
    enabled = true,
    reconnectEveryMs = 300
  }) {
    this.#pluginId = pluginId;
    this.#enabled = enabled;
    this.#eventTarget = this.getGlobalTarget;
    this.#debug = debug;
    this.debugLog(" Initializing event subscription for plugin", this.#pluginId);
    this.#queuedEvents = [];
    this.#connected = false;
    this.#connectIntervalId = null;
    this.#connectEveryMs = reconnectEveryMs;
  }
  startConnectLoop() {
    if (this.#connectIntervalId !== null || this.#connected) return;
    this.debugLog(`Starting connect loop (every ${this.#connectEveryMs}ms)`);
    this.#connectIntervalId = setInterval(
      this.#retryConnection,
      this.#connectEveryMs
    );
  }
  stopConnectLoop() {
    this.#connecting = false;
    if (this.#connectIntervalId === null) {
      return;
    }
    clearInterval(this.#connectIntervalId);
    this.#connectIntervalId = null;
    this.debugLog("Stopped connect loop");
  }
  debugLog(...args) {
    if (this.#debug) {
      console.log(`🌴 [tanstack-devtools:${this.#pluginId}-plugin]`, ...args);
    }
  }
  getGlobalTarget() {
    if (typeof globalThis !== "undefined" && globalThis.__TANSTACK_EVENT_TARGET__) {
      this.debugLog("Using global event target");
      return globalThis.__TANSTACK_EVENT_TARGET__;
    }
    if (typeof window !== "undefined" && typeof window.addEventListener !== "undefined") {
      this.debugLog("Using window as event target");
      return window;
    }
    const eventTarget = typeof EventTarget !== "undefined" ? new EventTarget() : void 0;
    if (typeof eventTarget === "undefined" || typeof eventTarget.addEventListener === "undefined") {
      this.debugLog(
        "No event mechanism available, running in non-web environment"
      );
      return {
        addEventListener: () => {
        },
        removeEventListener: () => {
        },
        dispatchEvent: () => false
      };
    }
    this.debugLog("Using new EventTarget as fallback");
    return eventTarget;
  }
  getPluginId() {
    return this.#pluginId;
  }
  dispatchCustomEventShim(eventName, detail) {
    try {
      const event = new Event(eventName, {
        detail
      });
      this.#eventTarget().dispatchEvent(event);
    } catch (e) {
      this.debugLog("Failed to dispatch shim event");
    }
  }
  dispatchCustomEvent(eventName, detail) {
    try {
      this.#eventTarget().dispatchEvent(new CustomEvent(eventName, { detail }));
    } catch (e) {
      this.dispatchCustomEventShim(eventName, detail);
    }
  }
  emitEventToBus(event) {
    this.debugLog("Emitting event to client bus", event);
    this.dispatchCustomEvent("tanstack-dispatch-event", event);
  }
  emit(eventSuffix, payload) {
    if (!this.#enabled) {
      this.debugLog(
        "Event bus client is disabled, not emitting event",
        eventSuffix,
        payload
      );
      return;
    }
    if (!this.#connected) {
      this.debugLog("Bus not available, will be pushed as soon as connected");
      this.#queuedEvents.push({
        type: `${this.#pluginId}:${eventSuffix}`,
        payload,
        pluginId: this.#pluginId
      });
      if (typeof CustomEvent !== "undefined" && !this.#connecting) {
        this.#connectFunction();
        this.startConnectLoop();
      }
      return;
    }
    return this.emitEventToBus({
      type: `${this.#pluginId}:${eventSuffix}`,
      payload,
      pluginId: this.#pluginId
    });
  }
  on(eventSuffix, cb) {
    const eventName = `${this.#pluginId}:${eventSuffix}`;
    if (!this.#enabled) {
      this.debugLog(
        "Event bus client is disabled, not registering event",
        eventName
      );
      return () => {
      };
    }
    const handler = (e) => {
      this.debugLog("Received event from bus", e.detail);
      cb(e.detail);
    };
    this.#eventTarget().addEventListener(eventName, handler);
    this.debugLog("Registered event to bus", eventName);
    return () => {
      this.#eventTarget().removeEventListener(eventName, handler);
    };
  }
  onAll(cb) {
    if (!this.#enabled) {
      this.debugLog("Event bus client is disabled, not registering event");
      return () => {
      };
    }
    const handler = (e) => {
      const event = e.detail;
      cb(event);
    };
    this.#eventTarget().addEventListener("tanstack-devtools-global", handler);
    return () => this.#eventTarget().removeEventListener(
      "tanstack-devtools-global",
      handler
    );
  }
  onAllPluginEvents(cb) {
    if (!this.#enabled) {
      this.debugLog("Event bus client is disabled, not registering event");
      return () => {
      };
    }
    const handler = (e) => {
      const event = e.detail;
      if (this.#pluginId && event.pluginId !== this.#pluginId) {
        return;
      }
      cb(event);
    };
    this.#eventTarget().addEventListener("tanstack-devtools-global", handler);
    return () => this.#eventTarget().removeEventListener(
      "tanstack-devtools-global",
      handler
    );
  }
}
class PacerEventClient extends EventClient {
  constructor(props) {
    super({
      pluginId: "pacer",
      debug: props?.debug
    });
  }
}
const emitChange = (event, payload) => {
  pacerEventClient.emit(event, payload);
};
const pacerEventClient = new PacerEventClient();
function getDefaultThrottlerState() {
  return {
    executionCount: 0,
    isPending: false,
    lastArgs: void 0,
    lastExecutionTime: 0,
    nextExecutionTime: 0,
    status: "idle",
    maybeExecuteCount: 0
  };
}
const defaultOptions = {
  enabled: true,
  leading: true,
  trailing: true,
  wait: 0
};
class Throttler {
  constructor(fn, initialOptions) {
    this.fn = fn;
    this.store = new Store(
      getDefaultThrottlerState()
    );
    this.setOptions = (newOptions) => {
      this.options = { ...this.options, ...newOptions };
      if (!this.#getEnabled()) {
        this.cancel();
      }
    };
    this.#setState = (newState) => {
      this.store.setState((state) => {
        const combinedState = {
          ...state,
          ...newState
        };
        const { isPending } = combinedState;
        return {
          ...combinedState,
          status: !this.#getEnabled() ? "disabled" : isPending ? "pending" : "idle"
        };
      });
      emitChange("Throttler", this);
    };
    this.#getEnabled = () => {
      return !!parseFunctionOrValue(this.options.enabled, this);
    };
    this.#getWait = () => {
      return parseFunctionOrValue(this.options.wait, this);
    };
    this.maybeExecute = (...args) => {
      this.#setState({
        maybeExecuteCount: this.store.state.maybeExecuteCount + 1
      });
      const now = Date.now();
      const timeSinceLastExecution = now - this.store.state.lastExecutionTime;
      const wait = this.#getWait();
      if (this.options.leading && timeSinceLastExecution >= wait) {
        this.#execute(...args);
      } else {
        this.#setState({
          lastArgs: args
        });
        if (!this.#timeoutId && this.options.trailing) {
          const _timeSinceLastExecution = this.store.state.lastExecutionTime ? now - this.store.state.lastExecutionTime : 0;
          const timeoutDuration = wait - _timeSinceLastExecution;
          this.#setState({ isPending: true });
          this.#timeoutId = setTimeout(() => {
            const { lastArgs } = this.store.state;
            if (lastArgs !== void 0) {
              this.#execute(...lastArgs);
            }
          }, timeoutDuration);
        }
      }
    };
    this.#execute = (...args) => {
      if (!this.#getEnabled()) return;
      this.fn(...args);
      const lastExecutionTime = Date.now();
      const nextExecutionTime = lastExecutionTime + this.#getWait();
      this.#clearTimeout();
      this.#setState({
        executionCount: this.store.state.executionCount + 1,
        lastExecutionTime,
        nextExecutionTime,
        isPending: false,
        lastArgs: void 0
      });
      this.options.onExecute?.(args, this);
      setTimeout(() => {
        if (!this.store.state.isPending) {
          this.#setState({ nextExecutionTime: void 0 });
        }
      }, this.#getWait());
    };
    this.flush = () => {
      if (this.store.state.isPending && this.store.state.lastArgs) {
        this.#execute(...this.store.state.lastArgs);
      }
    };
    this.#clearTimeout = () => {
      if (this.#timeoutId) {
        clearTimeout(this.#timeoutId);
        this.#timeoutId = void 0;
      }
    };
    this.cancel = () => {
      this.#clearTimeout();
      this.#setState({
        lastArgs: void 0,
        isPending: false
      });
    };
    this.reset = () => {
      this.#setState(getDefaultThrottlerState());
    };
    this.key = createKey(initialOptions.key);
    this.options = {
      ...defaultOptions,
      ...initialOptions
    };
    this.#setState(this.options.initialState ?? {});
    pacerEventClient.on("d-Throttler", (event) => {
      if (event.payload.key !== this.key) return;
      this.#setState(event.payload.store.state);
      this.setOptions(event.payload.options);
    });
  }
  #timeoutId;
  #setState;
  #getEnabled;
  #getWait;
  #execute;
  #clearTimeout;
}
function throttle(fn, initialOptions) {
  const throttler = new Throttler(fn, initialOptions);
  return throttler.maybeExecute;
}
function functionalUpdate(updater, input) {
  return typeof updater === "function" ? updater(input) : updater;
}
function getBy(obj, path) {
  const pathObj = makePathArray(path);
  return pathObj.reduce((current, pathPart) => {
    if (current === null) return null;
    if (typeof current !== "undefined") {
      return current[pathPart];
    }
    return void 0;
  }, obj);
}
function setBy(obj, _path, updater) {
  const path = makePathArray(_path);
  function doSet(parent) {
    if (!path.length) {
      return functionalUpdate(updater, parent);
    }
    const key = path.shift();
    if (typeof key === "string" || typeof key === "number" && !Array.isArray(parent)) {
      if (typeof parent === "object") {
        if (parent === null) {
          parent = {};
        }
        return {
          ...parent,
          [key]: doSet(parent[key])
        };
      }
      return {
        [key]: doSet()
      };
    }
    if (Array.isArray(parent) && typeof key === "number") {
      const prefix = parent.slice(0, key);
      return [
        ...prefix.length ? prefix : new Array(key),
        doSet(parent[key]),
        ...parent.slice(key + 1)
      ];
    }
    return [...new Array(key), doSet()];
  }
  return doSet(obj);
}
function deleteBy(obj, _path) {
  const path = makePathArray(_path);
  function doDelete(parent) {
    if (!parent) return;
    if (path.length === 1) {
      const finalPath = path[0];
      if (Array.isArray(parent) && typeof finalPath === "number") {
        return parent.filter((_, i) => i !== finalPath);
      }
      const { [finalPath]: remove, ...rest } = parent;
      return rest;
    }
    const key = path.shift();
    if (typeof key === "string") {
      if (typeof parent === "object") {
        return {
          ...parent,
          [key]: doDelete(parent[key])
        };
      }
    }
    if (typeof key === "number") {
      if (Array.isArray(parent)) {
        if (key >= parent.length) {
          return parent;
        }
        const prefix = parent.slice(0, key);
        return [
          ...prefix.length ? prefix : new Array(key),
          doDelete(parent[key]),
          ...parent.slice(key + 1)
        ];
      }
    }
    throw new Error("It seems we have created an infinite loop in deleteBy. ");
  }
  return doDelete(obj);
}
const reLineOfOnlyDigits = /^(\d+)$/gm;
const reDigitsBetweenDots = /\.(\d+)(?=\.)/gm;
const reStartWithDigitThenDot = /^(\d+)\./gm;
const reDotWithDigitsToEnd = /\.(\d+$)/gm;
const reMultipleDots = /\.{2,}/gm;
const intPrefix = "__int__";
const intReplace = `${intPrefix}$1`;
function makePathArray(str) {
  if (Array.isArray(str)) {
    return [...str];
  }
  if (typeof str !== "string") {
    throw new Error("Path must be a string.");
  }
  return str.replace(/(^\[)|]/gm, "").replace(/\[/g, ".").replace(reLineOfOnlyDigits, intReplace).replace(reDigitsBetweenDots, `.${intReplace}.`).replace(reStartWithDigitThenDot, `${intReplace}.`).replace(reDotWithDigitsToEnd, `.${intReplace}`).replace(reMultipleDots, ".").split(".").map((d) => {
    if (d.startsWith(intPrefix)) {
      const numStr = d.substring(intPrefix.length);
      const num = parseInt(numStr, 10);
      if (String(num) === numStr) {
        return num;
      }
      return numStr;
    }
    return d;
  });
}
function concatenatePaths(path1, path2) {
  if (path1.length === 0) return path2;
  if (path2.length === 0) return path1;
  if (path2.startsWith("[")) {
    return path1 + path2;
  }
  if (path2.startsWith(".")) {
    return path1 + path2;
  }
  return `${path1}.${path2}`;
}
function isNonEmptyArray(obj) {
  return !(Array.isArray(obj) && obj.length === 0);
}
function getSyncValidatorArray(cause, options) {
  const runValidation = (props) => {
    return props.validators.filter(Boolean).map((validator) => {
      return {
        cause: validator.cause,
        validate: validator.fn
      };
    });
  };
  return options.validationLogic({
    form: options.form,
    validators: options.validators,
    event: { type: cause, async: false },
    runValidation
  });
}
function getAsyncValidatorArray(cause, options) {
  const { asyncDebounceMs } = options;
  const {
    onBlurAsyncDebounceMs,
    onChangeAsyncDebounceMs,
    onDynamicAsyncDebounceMs
  } = options.validators || {};
  const defaultDebounceMs = asyncDebounceMs ?? 0;
  const runValidation = (props) => {
    return props.validators.filter(Boolean).map((validator) => {
      const validatorCause = validator?.cause || cause;
      let debounceMs = defaultDebounceMs;
      switch (validatorCause) {
        case "change":
          debounceMs = onChangeAsyncDebounceMs ?? defaultDebounceMs;
          break;
        case "blur":
          debounceMs = onBlurAsyncDebounceMs ?? defaultDebounceMs;
          break;
        case "dynamic":
          debounceMs = onDynamicAsyncDebounceMs ?? defaultDebounceMs;
          break;
        case "submit":
          debounceMs = 0;
          break;
      }
      if (cause === "submit") {
        debounceMs = 0;
      }
      return {
        cause: validatorCause,
        validate: validator.fn,
        debounceMs
      };
    });
  };
  return options.validationLogic({
    form: options.form,
    validators: options.validators,
    event: { type: cause, async: true },
    runValidation
  });
}
const isGlobalFormValidationError = (error) => {
  return !!error && typeof error === "object" && "fields" in error;
};
function evaluate(objA, objB) {
  if (Object.is(objA, objB)) {
    return true;
  }
  if (typeof objA !== "object" || objA === null || typeof objB !== "object" || objB === null) {
    return false;
  }
  if (objA instanceof Date && objB instanceof Date) {
    return objA.getTime() === objB.getTime();
  }
  if (objA instanceof Map && objB instanceof Map) {
    if (objA.size !== objB.size) return false;
    for (const [k, v] of objA) {
      if (!objB.has(k) || !Object.is(v, objB.get(k))) return false;
    }
    return true;
  }
  if (objA instanceof Set && objB instanceof Set) {
    if (objA.size !== objB.size) return false;
    for (const v of objA) {
      if (!objB.has(v)) return false;
    }
    return true;
  }
  const keysA = Object.keys(objA);
  const keysB = Object.keys(objB);
  if (keysA.length !== keysB.length) {
    return false;
  }
  for (const key of keysA) {
    if (!keysB.includes(key) || !evaluate(objA[key], objB[key])) {
      return false;
    }
  }
  return true;
}
const determineFormLevelErrorSourceAndValue = ({
  newFormValidatorError,
  isPreviousErrorFromFormValidator,
  previousErrorValue
}) => {
  if (newFormValidatorError) {
    return { newErrorValue: newFormValidatorError, newSource: "form" };
  }
  if (isPreviousErrorFromFormValidator) {
    return { newErrorValue: void 0, newSource: void 0 };
  }
  if (previousErrorValue) {
    return { newErrorValue: previousErrorValue, newSource: "field" };
  }
  return { newErrorValue: void 0, newSource: void 0 };
};
const determineFieldLevelErrorSourceAndValue = ({
  formLevelError,
  fieldLevelError
}) => {
  if (fieldLevelError) {
    return { newErrorValue: fieldLevelError, newSource: "field" };
  }
  if (formLevelError) {
    return { newErrorValue: formLevelError, newSource: "form" };
  }
  return { newErrorValue: void 0, newSource: void 0 };
};
function mergeOpts(originalOpts, overrides) {
  if (originalOpts === void 0 || originalOpts === null) {
    return overrides;
  }
  return { ...originalOpts, ...overrides };
}
let IDX = 256;
const HEX = [];
let BUFFER;
while (IDX--) {
  HEX[IDX] = (IDX + 256).toString(16).substring(1);
}
function uuid() {
  let i = 0;
  let num;
  let out = "";
  if (!BUFFER || IDX + 16 > 256) {
    BUFFER = new Array(256);
    i = 256;
    while (i--) {
      BUFFER[i] = 256 * Math.random() | 0;
    }
    i = 0;
    IDX = 0;
  }
  for (; i < 16; i++) {
    num = BUFFER[IDX + i];
    if (i === 6) out += HEX[num & 15 | 64];
    else if (i === 8) out += HEX[num & 63 | 128];
    else out += HEX[num];
    if (i & 1 && i > 1 && i < 11) out += "-";
  }
  IDX++;
  return out;
}
const defaultValidationLogic = (props) => {
  if (!props.validators) {
    return props.runValidation({
      validators: [],
      form: props.form
    });
  }
  const isAsync = props.event.async;
  const onMountValidator = isAsync ? void 0 : { fn: props.validators.onMount, cause: "mount" };
  const onChangeValidator = {
    fn: isAsync ? props.validators.onChangeAsync : props.validators.onChange,
    cause: "change"
  };
  const onBlurValidator = {
    fn: isAsync ? props.validators.onBlurAsync : props.validators.onBlur,
    cause: "blur"
  };
  const onSubmitValidator = {
    fn: isAsync ? props.validators.onSubmitAsync : props.validators.onSubmit,
    cause: "submit"
  };
  const onServerValidator = isAsync ? void 0 : { fn: () => void 0, cause: "server" };
  switch (props.event.type) {
    case "mount": {
      return props.runValidation({
        validators: [onMountValidator],
        form: props.form
      });
    }
    case "submit": {
      return props.runValidation({
        validators: [
          onChangeValidator,
          onBlurValidator,
          onSubmitValidator,
          onServerValidator
        ],
        form: props.form
      });
    }
    case "server": {
      return props.runValidation({
        validators: [],
        form: props.form
      });
    }
    case "blur": {
      return props.runValidation({
        validators: [onBlurValidator, onServerValidator],
        form: props.form
      });
    }
    case "change": {
      return props.runValidation({
        validators: [onChangeValidator, onServerValidator],
        form: props.form
      });
    }
    default: {
      throw new Error(`Unknown validation event type: ${props.event.type}`);
    }
  }
};
function prefixSchemaToErrors(issues, formValue) {
  const schema = /* @__PURE__ */ new Map();
  for (const issue of issues) {
    const issuePath = issue.path ?? [];
    let currentFormValue = formValue;
    let path = "";
    for (let i = 0; i < issuePath.length; i++) {
      const pathSegment = issuePath[i];
      if (pathSegment === void 0) continue;
      const segment = typeof pathSegment === "object" ? pathSegment.key : pathSegment;
      const segmentAsNumber = Number(segment);
      if (Array.isArray(currentFormValue) && !Number.isNaN(segmentAsNumber)) {
        path += `[${segmentAsNumber}]`;
      } else {
        path += (i > 0 ? "." : "") + String(segment);
      }
      if (typeof currentFormValue === "object" && currentFormValue !== null) {
        currentFormValue = currentFormValue[segment];
      } else {
        currentFormValue = void 0;
      }
    }
    schema.set(path, (schema.get(path) ?? []).concat(issue));
  }
  return Object.fromEntries(schema);
}
const transformFormIssues = (issues, formValue) => {
  const schemaErrors = prefixSchemaToErrors(issues, formValue);
  return {
    form: schemaErrors,
    fields: schemaErrors
  };
};
const standardSchemaValidators = {
  validate({
    value,
    validationSource
  }, schema) {
    const result = schema["~standard"].validate(value);
    if (result instanceof Promise) {
      throw new Error("async function passed to sync validator");
    }
    if (!result.issues) return;
    if (validationSource === "field")
      return result.issues;
    return transformFormIssues(result.issues, value);
  },
  async validateAsync({
    value,
    validationSource
  }, schema) {
    const result = await schema["~standard"].validate(value);
    if (!result.issues) return;
    if (validationSource === "field")
      return result.issues;
    return transformFormIssues(result.issues, value);
  }
};
const isStandardSchemaValidator = (validator) => !!validator && "~standard" in validator;
const defaultFieldMeta = {
  isValidating: false,
  isTouched: false,
  isBlurred: false,
  isDirty: false,
  isPristine: true,
  isValid: true,
  isDefaultValue: true,
  errors: [],
  errorMap: {},
  errorSourceMap: {}
};
function metaHelper(formApi) {
  function handleArrayFieldMetaShift(field, index, mode, secondIndex) {
    const affectedFields = getAffectedFields(field, index, mode, secondIndex);
    const handlers = {
      insert: () => handleInsertMode(affectedFields, field, index),
      remove: () => handleRemoveMode(affectedFields),
      swap: () => secondIndex !== void 0 && handleSwapMode(affectedFields, field, index, secondIndex),
      move: () => secondIndex !== void 0 && handleMoveMode(affectedFields, field, index, secondIndex)
    };
    handlers[mode]();
  }
  function getFieldPath(field, index) {
    return `${field}[${index}]`;
  }
  function getAffectedFields(field, index, mode, secondIndex) {
    const affectedFieldKeys = [getFieldPath(field, index)];
    if (mode === "swap") {
      affectedFieldKeys.push(getFieldPath(field, secondIndex));
    } else if (mode === "move") {
      const [startIndex, endIndex] = [
        Math.min(index, secondIndex),
        Math.max(index, secondIndex)
      ];
      for (let i = startIndex; i <= endIndex; i++) {
        affectedFieldKeys.push(getFieldPath(field, i));
      }
    } else {
      const currentValue = formApi.getFieldValue(field);
      const fieldItems = Array.isArray(currentValue) ? currentValue.length : 0;
      for (let i = index + 1; i < fieldItems; i++) {
        affectedFieldKeys.push(getFieldPath(field, i));
      }
    }
    return Object.keys(formApi.fieldInfo).filter(
      (fieldKey) => affectedFieldKeys.some((key) => fieldKey.startsWith(key))
    );
  }
  function updateIndex(fieldKey, direction) {
    return fieldKey.replace(/\[(\d+)\]/, (_, num) => {
      const currIndex = parseInt(num, 10);
      const newIndex = direction === "up" ? currIndex + 1 : Math.max(0, currIndex - 1);
      return `[${newIndex}]`;
    });
  }
  function shiftMeta(fields, direction) {
    const sortedFields = direction === "up" ? fields : [...fields].reverse();
    sortedFields.forEach((fieldKey) => {
      const nextFieldKey = updateIndex(fieldKey.toString(), direction);
      const nextFieldMeta = formApi.getFieldMeta(nextFieldKey);
      if (nextFieldMeta) {
        formApi.setFieldMeta(fieldKey, nextFieldMeta);
      } else {
        formApi.setFieldMeta(fieldKey, getEmptyFieldMeta());
      }
    });
  }
  const getEmptyFieldMeta = () => defaultFieldMeta;
  const handleInsertMode = (fields, field, insertIndex) => {
    shiftMeta(fields, "down");
    fields.forEach((fieldKey) => {
      if (fieldKey.toString().startsWith(getFieldPath(field, insertIndex))) {
        formApi.setFieldMeta(fieldKey, getEmptyFieldMeta());
      }
    });
  };
  const handleRemoveMode = (fields) => {
    shiftMeta(fields, "up");
  };
  const handleMoveMode = (fields, field, fromIndex, toIndex) => {
    const fromFields = new Map(
      Object.keys(formApi.fieldInfo).filter(
        (fieldKey) => fieldKey.startsWith(getFieldPath(field, fromIndex))
      ).map((fieldKey) => [
        fieldKey,
        formApi.getFieldMeta(fieldKey)
      ])
    );
    shiftMeta(fields, fromIndex < toIndex ? "up" : "down");
    Object.keys(formApi.fieldInfo).filter((fieldKey) => fieldKey.startsWith(getFieldPath(field, toIndex))).forEach((fieldKey) => {
      const fromKey = fieldKey.replace(
        getFieldPath(field, toIndex),
        getFieldPath(field, fromIndex)
      );
      const fromMeta = fromFields.get(fromKey);
      if (fromMeta) {
        formApi.setFieldMeta(fieldKey, fromMeta);
      }
    });
  };
  const handleSwapMode = (fields, field, index, secondIndex) => {
    fields.forEach((fieldKey) => {
      if (!fieldKey.toString().startsWith(getFieldPath(field, index))) return;
      const swappedKey = fieldKey.toString().replace(
        getFieldPath(field, index),
        getFieldPath(field, secondIndex)
      );
      const [meta1, meta2] = [
        formApi.getFieldMeta(fieldKey),
        formApi.getFieldMeta(swappedKey)
      ];
      if (meta1) formApi.setFieldMeta(swappedKey, meta1);
      if (meta2) formApi.setFieldMeta(fieldKey, meta2);
    });
  };
  return { handleArrayFieldMetaShift };
}
class FormEventClient extends EventClient {
  constructor() {
    super({
      pluginId: "form-devtools",
      reconnectEveryMs: 1e3
    });
  }
}
const formEventClient = new FormEventClient();
function getDefaultFormState(defaultState) {
  return {
    values: defaultState.values ?? {},
    errorMap: defaultState.errorMap ?? {},
    fieldMetaBase: defaultState.fieldMetaBase ?? {},
    isSubmitted: defaultState.isSubmitted ?? false,
    isSubmitting: defaultState.isSubmitting ?? false,
    isValidating: defaultState.isValidating ?? false,
    submissionAttempts: defaultState.submissionAttempts ?? 0,
    isSubmitSuccessful: defaultState.isSubmitSuccessful ?? false,
    validationMetaMap: defaultState.validationMetaMap ?? {
      onChange: void 0,
      onBlur: void 0,
      onSubmit: void 0,
      onMount: void 0,
      onServer: void 0,
      onDynamic: void 0
    }
  };
}
class FormApi {
  /**
   * Constructs a new `FormApi` instance with the given form options.
   */
  constructor(opts) {
    this.options = {};
    this.fieldInfo = {};
    this.prevTransformArray = [];
    this.mount = () => {
      const cleanupFieldMetaDerived = this.fieldMetaDerived.mount();
      const cleanupStoreDerived = this.store.mount();
      const cleanup = () => {
        cleanupFieldMetaDerived();
        cleanupStoreDerived();
        formEventClient.emit("form-unmounted", {
          id: this._formId
        });
      };
      this.options.listeners?.onMount?.({ formApi: this });
      const { onMount } = this.options.validators || {};
      formEventClient.emit("form-api", {
        id: this._formId,
        state: this.store.state,
        options: this.options
      });
      if (!onMount) return cleanup;
      this.validateSync("mount");
      return cleanup;
    };
    this.update = (options) => {
      if (!options) return;
      const oldOptions = this.options;
      this.options = options;
      const shouldUpdateReeval = !!options.transform?.deps?.some(
        (val, i) => val !== this.prevTransformArray[i]
      );
      const shouldUpdateValues = options.defaultValues && !evaluate(options.defaultValues, oldOptions.defaultValues) && !this.state.isTouched;
      const shouldUpdateState = !evaluate(options.defaultState, oldOptions.defaultState) && !this.state.isTouched;
      if (!shouldUpdateValues && !shouldUpdateState && !shouldUpdateReeval) return;
      batch(() => {
        this.baseStore.setState(
          () => getDefaultFormState(
            Object.assign(
              {},
              this.state,
              shouldUpdateState ? options.defaultState : {},
              shouldUpdateValues ? {
                values: options.defaultValues
              } : {},
              shouldUpdateReeval ? { _force_re_eval: !this.state._force_re_eval } : {}
            )
          )
        );
      });
      formEventClient.emit("form-api", {
        id: this._formId,
        state: this.store.state,
        options: this.options
      });
    };
    this.reset = (values, opts2) => {
      const { fieldMeta: currentFieldMeta } = this.state;
      const fieldMetaBase = this.resetFieldMeta(currentFieldMeta);
      if (values && !opts2?.keepDefaultValues) {
        this.options = {
          ...this.options,
          defaultValues: values
        };
      }
      this.baseStore.setState(
        () => getDefaultFormState({
          ...this.options.defaultState,
          values: values ?? this.options.defaultValues ?? this.options.defaultState?.values,
          fieldMetaBase
        })
      );
    };
    this.validateAllFields = async (cause) => {
      const fieldValidationPromises = [];
      batch(() => {
        void Object.values(this.fieldInfo).forEach(
          (field) => {
            if (!field.instance) return;
            const fieldInstance = field.instance;
            fieldValidationPromises.push(
              // Remember, `validate` is either a sync operation or a promise
              Promise.resolve().then(
                () => fieldInstance.validate(cause, { skipFormValidation: true })
              )
            );
            if (!field.instance.state.meta.isTouched) {
              field.instance.setMeta((prev) => ({ ...prev, isTouched: true }));
            }
          }
        );
      });
      const fieldErrorMapMap = await Promise.all(fieldValidationPromises);
      return fieldErrorMapMap.flat();
    };
    this.validateArrayFieldsStartingFrom = async (field, index, cause) => {
      const currentValue = this.getFieldValue(field);
      const lastIndex = Array.isArray(currentValue) ? Math.max(currentValue.length - 1, 0) : null;
      const fieldKeysToValidate = [`${field}[${index}]`];
      for (let i = index + 1; i <= (lastIndex ?? 0); i++) {
        fieldKeysToValidate.push(`${field}[${i}]`);
      }
      const fieldsToValidate = Object.keys(this.fieldInfo).filter(
        (fieldKey) => fieldKeysToValidate.some((key) => fieldKey.startsWith(key))
      );
      const fieldValidationPromises = [];
      batch(() => {
        fieldsToValidate.forEach((nestedField) => {
          fieldValidationPromises.push(
            Promise.resolve().then(() => this.validateField(nestedField, cause))
          );
        });
      });
      const fieldErrorMapMap = await Promise.all(fieldValidationPromises);
      return fieldErrorMapMap.flat();
    };
    this.validateField = (field, cause) => {
      const fieldInstance = this.fieldInfo[field]?.instance;
      if (!fieldInstance) return [];
      if (!fieldInstance.state.meta.isTouched) {
        fieldInstance.setMeta((prev) => ({ ...prev, isTouched: true }));
      }
      return fieldInstance.validate(cause);
    };
    this.validateSync = (cause) => {
      const validates = getSyncValidatorArray(cause, {
        ...this.options,
        form: this,
        validationLogic: this.options.validationLogic || defaultValidationLogic
      });
      let hasErrored = false;
      const currentValidationErrorMap = {};
      batch(() => {
        for (const validateObj of validates) {
          if (!validateObj.validate) continue;
          const rawError = this.runValidator({
            validate: validateObj.validate,
            value: {
              value: this.state.values,
              formApi: this,
              validationSource: "form"
            },
            type: "validate"
          });
          const { formError, fieldErrors } = normalizeError$1(rawError);
          const errorMapKey = getErrorMapKey$1(validateObj.cause);
          for (const field of Object.keys(
            this.state.fieldMeta
          )) {
            if (this.baseStore.state.fieldMetaBase[field] === void 0) {
              continue;
            }
            const fieldMeta = this.getFieldMeta(field);
            if (!fieldMeta) continue;
            const {
              errorMap: currentErrorMap,
              errorSourceMap: currentErrorMapSource
            } = fieldMeta;
            const newFormValidatorError = fieldErrors?.[field];
            const { newErrorValue, newSource } = determineFormLevelErrorSourceAndValue({
              newFormValidatorError,
              isPreviousErrorFromFormValidator: (
                // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
                currentErrorMapSource?.[errorMapKey] === "form"
              ),
              // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
              previousErrorValue: currentErrorMap?.[errorMapKey]
            });
            if (newSource === "form") {
              currentValidationErrorMap[field] = {
                ...currentValidationErrorMap[field],
                [errorMapKey]: newFormValidatorError
              };
            }
            if (
              // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
              currentErrorMap?.[errorMapKey] !== newErrorValue
            ) {
              this.setFieldMeta(field, (prev) => ({
                ...prev,
                errorMap: {
                  ...prev.errorMap,
                  [errorMapKey]: newErrorValue
                },
                errorSourceMap: {
                  ...prev.errorSourceMap,
                  [errorMapKey]: newSource
                }
              }));
            }
          }
          if (this.state.errorMap?.[errorMapKey] !== formError) {
            this.baseStore.setState((prev) => ({
              ...prev,
              errorMap: {
                ...prev.errorMap,
                [errorMapKey]: formError
              }
            }));
          }
          if (formError || fieldErrors) {
            hasErrored = true;
          }
        }
        const submitErrKey = getErrorMapKey$1("submit");
        if (
          // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
          this.state.errorMap?.[submitErrKey] && cause !== "submit" && !hasErrored
        ) {
          this.baseStore.setState((prev) => ({
            ...prev,
            errorMap: {
              ...prev.errorMap,
              [submitErrKey]: void 0
            }
          }));
        }
        const serverErrKey = getErrorMapKey$1("server");
        if (
          // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
          this.state.errorMap?.[serverErrKey] && cause !== "server" && !hasErrored
        ) {
          this.baseStore.setState((prev) => ({
            ...prev,
            errorMap: {
              ...prev.errorMap,
              [serverErrKey]: void 0
            }
          }));
        }
      });
      return { hasErrored, fieldsErrorMap: currentValidationErrorMap };
    };
    this.validateAsync = async (cause) => {
      const validates = getAsyncValidatorArray(cause, {
        ...this.options,
        form: this,
        validationLogic: this.options.validationLogic || defaultValidationLogic
      });
      if (!this.state.isFormValidating) {
        this.baseStore.setState((prev) => ({ ...prev, isFormValidating: true }));
      }
      const promises = [];
      let fieldErrorsFromFormValidators;
      for (const validateObj of validates) {
        if (!validateObj.validate) continue;
        const key = getErrorMapKey$1(validateObj.cause);
        const fieldValidatorMeta = this.state.validationMetaMap[key];
        fieldValidatorMeta?.lastAbortController.abort();
        const controller = new AbortController();
        this.state.validationMetaMap[key] = {
          lastAbortController: controller
        };
        promises.push(
          new Promise(async (resolve) => {
            let rawError;
            try {
              rawError = await new Promise((rawResolve, rawReject) => {
                setTimeout(async () => {
                  if (controller.signal.aborted) return rawResolve(void 0);
                  try {
                    rawResolve(
                      await this.runValidator({
                        validate: validateObj.validate,
                        value: {
                          value: this.state.values,
                          formApi: this,
                          validationSource: "form",
                          signal: controller.signal
                        },
                        type: "validateAsync"
                      })
                    );
                  } catch (e) {
                    rawReject(e);
                  }
                }, validateObj.debounceMs);
              });
            } catch (e) {
              rawError = e;
            }
            const { formError, fieldErrors: fieldErrorsFromNormalizeError } = normalizeError$1(rawError);
            if (fieldErrorsFromNormalizeError) {
              fieldErrorsFromFormValidators = fieldErrorsFromFormValidators ? {
                ...fieldErrorsFromFormValidators,
                ...fieldErrorsFromNormalizeError
              } : fieldErrorsFromNormalizeError;
            }
            const errorMapKey = getErrorMapKey$1(validateObj.cause);
            for (const field of Object.keys(
              this.state.fieldMeta
            )) {
              if (this.baseStore.state.fieldMetaBase[field] === void 0) {
                continue;
              }
              const fieldMeta = this.getFieldMeta(field);
              if (!fieldMeta) continue;
              const {
                errorMap: currentErrorMap,
                errorSourceMap: currentErrorMapSource
              } = fieldMeta;
              const newFormValidatorError = fieldErrorsFromFormValidators?.[field];
              const { newErrorValue, newSource } = determineFormLevelErrorSourceAndValue({
                newFormValidatorError,
                isPreviousErrorFromFormValidator: (
                  // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
                  currentErrorMapSource?.[errorMapKey] === "form"
                ),
                // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
                previousErrorValue: currentErrorMap?.[errorMapKey]
              });
              if (
                // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
                currentErrorMap?.[errorMapKey] !== newErrorValue
              ) {
                this.setFieldMeta(field, (prev) => ({
                  ...prev,
                  errorMap: {
                    ...prev.errorMap,
                    [errorMapKey]: newErrorValue
                  },
                  errorSourceMap: {
                    ...prev.errorSourceMap,
                    [errorMapKey]: newSource
                  }
                }));
              }
            }
            this.baseStore.setState((prev) => ({
              ...prev,
              errorMap: {
                ...prev.errorMap,
                [errorMapKey]: formError
              }
            }));
            resolve(
              fieldErrorsFromFormValidators ? { fieldErrors: fieldErrorsFromFormValidators, errorMapKey } : void 0
            );
          })
        );
      }
      let results = [];
      const fieldsErrorMap = {};
      if (promises.length) {
        results = await Promise.all(promises);
        for (const fieldValidationResult of results) {
          if (fieldValidationResult?.fieldErrors) {
            const { errorMapKey } = fieldValidationResult;
            for (const [field, fieldError] of Object.entries(
              fieldValidationResult.fieldErrors
            )) {
              const oldErrorMap = fieldsErrorMap[field] || {};
              const newErrorMap = {
                ...oldErrorMap,
                [errorMapKey]: fieldError
              };
              fieldsErrorMap[field] = newErrorMap;
            }
          }
        }
      }
      this.baseStore.setState((prev) => ({
        ...prev,
        isFormValidating: false
      }));
      return fieldsErrorMap;
    };
    this.validate = (cause) => {
      const { hasErrored, fieldsErrorMap } = this.validateSync(cause);
      if (hasErrored && !this.options.asyncAlways) {
        return fieldsErrorMap;
      }
      return this.validateAsync(cause);
    };
    this.getFieldValue = (field) => getBy(this.state.values, field);
    this.getFieldMeta = (field) => {
      return this.state.fieldMeta[field];
    };
    this.getFieldInfo = (field) => {
      return this.fieldInfo[field] ||= {
        instance: null,
        validationMetaMap: {
          onChange: void 0,
          onBlur: void 0,
          onSubmit: void 0,
          onMount: void 0,
          onServer: void 0,
          onDynamic: void 0
        }
      };
    };
    this.setFieldMeta = (field, updater) => {
      this.baseStore.setState((prev) => {
        return {
          ...prev,
          fieldMetaBase: {
            ...prev.fieldMetaBase,
            [field]: functionalUpdate(
              updater,
              prev.fieldMetaBase[field]
            )
          }
        };
      });
    };
    this.resetFieldMeta = (fieldMeta) => {
      return Object.keys(fieldMeta).reduce(
        (acc, key) => {
          const fieldKey = key;
          acc[fieldKey] = defaultFieldMeta;
          return acc;
        },
        {}
      );
    };
    this.setFieldValue = (field, updater, opts2) => {
      const dontUpdateMeta = opts2?.dontUpdateMeta ?? false;
      const dontRunListeners = opts2?.dontRunListeners ?? false;
      const dontValidate = opts2?.dontValidate ?? false;
      batch(() => {
        if (!dontUpdateMeta) {
          this.setFieldMeta(field, (prev) => ({
            ...prev,
            isTouched: true,
            isDirty: true,
            errorMap: {
              // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
              ...prev?.errorMap,
              onMount: void 0
            }
          }));
        }
        this.baseStore.setState((prev) => {
          return {
            ...prev,
            values: setBy(prev.values, field, updater)
          };
        });
      });
      if (!dontRunListeners) {
        this.getFieldInfo(field).instance?.triggerOnChangeListener();
      }
      if (!dontValidate) {
        this.validateField(field, "change");
      }
    };
    this.deleteField = (field) => {
      const subFieldsToDelete = Object.keys(this.fieldInfo).filter((f) => {
        const fieldStr = field.toString();
        return f !== fieldStr && f.startsWith(fieldStr);
      });
      const fieldsToDelete = [...subFieldsToDelete, field];
      this.baseStore.setState((prev) => {
        const newState = { ...prev };
        fieldsToDelete.forEach((f) => {
          newState.values = deleteBy(newState.values, f);
          delete this.fieldInfo[f];
          delete newState.fieldMetaBase[f];
        });
        return newState;
      });
    };
    this.pushFieldValue = (field, value, options) => {
      this.setFieldValue(
        field,
        (prev) => [...Array.isArray(prev) ? prev : [], value],
        options
      );
    };
    this.insertFieldValue = async (field, index, value, options) => {
      this.setFieldValue(
        field,
        (prev) => {
          return [
            ...prev.slice(0, index),
            value,
            ...prev.slice(index)
          ];
        },
        mergeOpts(options, { dontValidate: true })
      );
      const dontValidate = options?.dontValidate ?? false;
      if (!dontValidate) {
        await this.validateField(field, "change");
      }
      metaHelper(this).handleArrayFieldMetaShift(field, index, "insert");
      if (!dontValidate) {
        await this.validateArrayFieldsStartingFrom(field, index, "change");
      }
    };
    this.replaceFieldValue = async (field, index, value, options) => {
      this.setFieldValue(
        field,
        (prev) => {
          return prev.map(
            (d, i) => i === index ? value : d
          );
        },
        mergeOpts(options, { dontValidate: true })
      );
      const dontValidate = options?.dontValidate ?? false;
      if (!dontValidate) {
        await this.validateField(field, "change");
        await this.validateArrayFieldsStartingFrom(field, index, "change");
      }
    };
    this.removeFieldValue = async (field, index, options) => {
      const fieldValue = this.getFieldValue(field);
      const lastIndex = Array.isArray(fieldValue) ? Math.max(fieldValue.length - 1, 0) : null;
      this.setFieldValue(
        field,
        (prev) => {
          return prev.filter(
            (_d, i) => i !== index
          );
        },
        mergeOpts(options, { dontValidate: true })
      );
      metaHelper(this).handleArrayFieldMetaShift(field, index, "remove");
      if (lastIndex !== null) {
        const start = `${field}[${lastIndex}]`;
        this.deleteField(start);
      }
      const dontValidate = options?.dontValidate ?? false;
      if (!dontValidate) {
        await this.validateField(field, "change");
        await this.validateArrayFieldsStartingFrom(field, index, "change");
      }
    };
    this.swapFieldValues = (field, index1, index2, options) => {
      this.setFieldValue(
        field,
        (prev) => {
          const prev1 = prev[index1];
          const prev2 = prev[index2];
          return setBy(setBy(prev, `${index1}`, prev2), `${index2}`, prev1);
        },
        mergeOpts(options, { dontValidate: true })
      );
      metaHelper(this).handleArrayFieldMetaShift(field, index1, "swap", index2);
      const dontValidate = options?.dontValidate ?? false;
      if (!dontValidate) {
        this.validateField(field, "change");
        this.validateField(`${field}[${index1}]`, "change");
        this.validateField(`${field}[${index2}]`, "change");
      }
    };
    this.moveFieldValues = (field, index1, index2, options) => {
      this.setFieldValue(
        field,
        (prev) => {
          const next = [...prev];
          next.splice(index2, 0, next.splice(index1, 1)[0]);
          return next;
        },
        mergeOpts(options, { dontValidate: true })
      );
      metaHelper(this).handleArrayFieldMetaShift(field, index1, "move", index2);
      const dontValidate = options?.dontValidate ?? false;
      if (!dontValidate) {
        this.validateField(field, "change");
        this.validateField(`${field}[${index1}]`, "change");
        this.validateField(`${field}[${index2}]`, "change");
      }
    };
    this.clearFieldValues = (field, options) => {
      const fieldValue = this.getFieldValue(field);
      const lastIndex = Array.isArray(fieldValue) ? Math.max(fieldValue.length - 1, 0) : null;
      this.setFieldValue(
        field,
        [],
        mergeOpts(options, { dontValidate: true })
      );
      if (lastIndex !== null) {
        for (let i = 0; i <= lastIndex; i++) {
          const fieldKey = `${field}[${i}]`;
          this.deleteField(fieldKey);
        }
      }
      const dontValidate = options?.dontValidate ?? false;
      if (!dontValidate) {
        this.validateField(field, "change");
      }
    };
    this.resetField = (field) => {
      this.baseStore.setState((prev) => {
        return {
          ...prev,
          fieldMetaBase: {
            ...prev.fieldMetaBase,
            [field]: defaultFieldMeta
          },
          values: this.options.defaultValues ? setBy(prev.values, field, getBy(this.options.defaultValues, field)) : prev.values
        };
      });
    };
    this.getAllErrors = () => {
      return {
        form: {
          errors: this.state.errors,
          errorMap: this.state.errorMap
        },
        fields: Object.entries(this.state.fieldMeta).reduce(
          (acc, [fieldName, fieldMeta]) => {
            if (Object.keys(fieldMeta).length && fieldMeta.errors.length) {
              acc[fieldName] = {
                errors: fieldMeta.errors,
                errorMap: fieldMeta.errorMap
              };
            }
            return acc;
          },
          {}
        )
      };
    };
    this.parseValuesWithSchema = (schema) => {
      return standardSchemaValidators.validate(
        { value: this.state.values, validationSource: "form" },
        schema
      );
    };
    this.parseValuesWithSchemaAsync = (schema) => {
      return standardSchemaValidators.validateAsync(
        { value: this.state.values, validationSource: "form" },
        schema
      );
    };
    this.timeoutIds = {
      validations: {},
      listeners: {},
      formListeners: {}
    };
    this._formId = opts?.formId ?? uuid();
    this._devtoolsSubmissionOverride = false;
    this.baseStore = new Store(
      getDefaultFormState({
        ...opts?.defaultState,
        values: opts?.defaultValues ?? opts?.defaultState?.values
      })
    );
    this.fieldMetaDerived = new Derived({
      deps: [this.baseStore],
      fn: ({ prevDepVals, currDepVals, prevVal: _prevVal }) => {
        const prevVal = _prevVal;
        const prevBaseStore = prevDepVals?.[0];
        const currBaseStore = currDepVals[0];
        let originalMetaCount = 0;
        const fieldMeta = {};
        for (const fieldName of Object.keys(
          currBaseStore.fieldMetaBase
        )) {
          const currBaseMeta = currBaseStore.fieldMetaBase[fieldName];
          const prevBaseMeta = prevBaseStore?.fieldMetaBase[fieldName];
          const prevFieldInfo = prevVal?.[fieldName];
          const curFieldVal = getBy(currBaseStore.values, fieldName);
          let fieldErrors = prevFieldInfo?.errors;
          if (!prevBaseMeta || currBaseMeta.errorMap !== prevBaseMeta.errorMap) {
            fieldErrors = Object.values(currBaseMeta.errorMap ?? {}).filter(
              (val) => val !== void 0
            );
            const fieldInstance = this.getFieldInfo(fieldName)?.instance;
            if (fieldInstance && !fieldInstance.options.disableErrorFlat) {
              fieldErrors = fieldErrors?.flat(
                1
              );
            }
          }
          const isFieldValid = !isNonEmptyArray(fieldErrors ?? []);
          const isFieldPristine = !currBaseMeta.isDirty;
          const isDefaultValue = evaluate(
            curFieldVal,
            getBy(this.options.defaultValues, fieldName)
          ) || evaluate(
            curFieldVal,
            // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
            this.getFieldInfo(fieldName)?.instance?.options.defaultValue
          );
          if (prevFieldInfo && prevFieldInfo.isPristine === isFieldPristine && prevFieldInfo.isValid === isFieldValid && prevFieldInfo.isDefaultValue === isDefaultValue && prevFieldInfo.errors === fieldErrors && currBaseMeta === prevBaseMeta) {
            fieldMeta[fieldName] = prevFieldInfo;
            originalMetaCount++;
            continue;
          }
          fieldMeta[fieldName] = {
            ...currBaseMeta,
            errors: fieldErrors,
            isPristine: isFieldPristine,
            isValid: isFieldValid,
            isDefaultValue
          };
        }
        if (!Object.keys(currBaseStore.fieldMetaBase).length) return fieldMeta;
        if (prevVal && originalMetaCount === Object.keys(currBaseStore.fieldMetaBase).length) {
          return prevVal;
        }
        return fieldMeta;
      }
    });
    this.store = new Derived({
      deps: [this.baseStore, this.fieldMetaDerived],
      fn: ({ prevDepVals, currDepVals, prevVal: _prevVal }) => {
        const prevVal = _prevVal;
        const prevBaseStore = prevDepVals?.[0];
        const currBaseStore = currDepVals[0];
        const currFieldMeta = currDepVals[1];
        const fieldMetaValues = Object.values(currFieldMeta).filter(
          Boolean
        );
        const isFieldsValidating = fieldMetaValues.some(
          (field) => field.isValidating
        );
        const isFieldsValid = fieldMetaValues.every((field) => field.isValid);
        const isTouched = fieldMetaValues.some((field) => field.isTouched);
        const isBlurred = fieldMetaValues.some((field) => field.isBlurred);
        const isDefaultValue = fieldMetaValues.every(
          (field) => field.isDefaultValue
        );
        const shouldInvalidateOnMount = (
          // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
          isTouched && currBaseStore.errorMap?.onMount
        );
        const isDirty = fieldMetaValues.some((field) => field.isDirty);
        const isPristine = !isDirty;
        const hasOnMountError = Boolean(
          // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
          currBaseStore.errorMap?.onMount || // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
          fieldMetaValues.some((f) => f?.errorMap?.onMount)
        );
        const isValidating = !!isFieldsValidating;
        let errors = prevVal?.errors ?? [];
        if (!prevBaseStore || currBaseStore.errorMap !== prevBaseStore.errorMap) {
          errors = Object.values(currBaseStore.errorMap).reduce((prev, curr) => {
            if (curr === void 0) return prev;
            if (curr && isGlobalFormValidationError(curr)) {
              prev.push(curr.form);
              return prev;
            }
            prev.push(curr);
            return prev;
          }, []);
        }
        const isFormValid = errors.length === 0;
        const isValid = isFieldsValid && isFormValid;
        const submitInvalid = this.options.canSubmitWhenInvalid ?? false;
        const canSubmit = currBaseStore.submissionAttempts === 0 && !isTouched && !hasOnMountError || !isValidating && !currBaseStore.isSubmitting && isValid || submitInvalid;
        let errorMap = currBaseStore.errorMap;
        if (shouldInvalidateOnMount) {
          errors = errors.filter(
            (err) => err !== currBaseStore.errorMap.onMount
          );
          errorMap = Object.assign(errorMap, { onMount: void 0 });
        }
        if (prevVal && prevBaseStore && prevVal.errorMap === errorMap && prevVal.fieldMeta === this.fieldMetaDerived.state && prevVal.errors === errors && prevVal.isFieldsValidating === isFieldsValidating && prevVal.isFieldsValid === isFieldsValid && prevVal.isFormValid === isFormValid && prevVal.isValid === isValid && prevVal.canSubmit === canSubmit && prevVal.isTouched === isTouched && prevVal.isBlurred === isBlurred && prevVal.isPristine === isPristine && prevVal.isDefaultValue === isDefaultValue && prevVal.isDirty === isDirty && evaluate(prevBaseStore, currBaseStore)) {
          return prevVal;
        }
        let state = {
          ...currBaseStore,
          errorMap,
          fieldMeta: this.fieldMetaDerived.state,
          errors,
          isFieldsValidating,
          isFieldsValid,
          isFormValid,
          isValid,
          canSubmit,
          isTouched,
          isBlurred,
          isPristine,
          isDefaultValue,
          isDirty
        };
        const transformArray = this.options.transform?.deps ?? [];
        const shouldTransform = transformArray.length !== this.prevTransformArray.length || transformArray.some((val, i) => val !== this.prevTransformArray[i]);
        if (shouldTransform) {
          const newObj = Object.assign({}, this, { state });
          this.options.transform?.fn(newObj);
          state = newObj.state;
          this.prevTransformArray = transformArray;
        }
        return state;
      }
    });
    this.handleSubmit = this.handleSubmit.bind(this);
    this.update(opts || {});
    const debouncedDevtoolState = throttle(
      (state) => formEventClient.emit("form-state", {
        id: this._formId,
        state
      }),
      {
        wait: 300
      }
    );
    this.store.subscribe(() => {
      debouncedDevtoolState(this.store.state);
    });
    formEventClient.on("request-form-state", (e) => {
      if (e.payload.id === this._formId) {
        formEventClient.emit("form-api", {
          id: this._formId,
          state: this.store.state,
          options: this.options
        });
      }
    });
    formEventClient.on("request-form-reset", (e) => {
      if (e.payload.id === this._formId) {
        this.reset();
      }
    });
    formEventClient.on("request-form-force-submit", (e) => {
      if (e.payload.id === this._formId) {
        this._devtoolsSubmissionOverride = true;
        this.handleSubmit();
        this._devtoolsSubmissionOverride = false;
      }
    });
  }
  get state() {
    return this.store.state;
  }
  get formId() {
    return this._formId;
  }
  /**
   * @private
   */
  runValidator(props) {
    if (isStandardSchemaValidator(props.validate)) {
      return standardSchemaValidators[props.type](
        props.value,
        props.validate
      );
    }
    return props.validate(props.value);
  }
  async handleSubmit(submitMeta) {
    this.baseStore.setState((old) => ({
      ...old,
      // Submission attempts mark the form as not submitted
      isSubmitted: false,
      // Count submission attempts
      submissionAttempts: old.submissionAttempts + 1,
      isSubmitSuccessful: false
      // Reset isSubmitSuccessful at the start of submission
    }));
    batch(() => {
      void Object.values(this.fieldInfo).forEach(
        (field) => {
          if (!field.instance) return;
          if (!field.instance.state.meta.isTouched) {
            field.instance.setMeta((prev) => ({ ...prev, isTouched: true }));
          }
        }
      );
    });
    const submitMetaArg = submitMeta ?? this.options.onSubmitMeta;
    if (!this.state.canSubmit && !this._devtoolsSubmissionOverride) {
      this.options.onSubmitInvalid?.({
        value: this.state.values,
        formApi: this,
        meta: submitMetaArg
      });
      return;
    }
    this.baseStore.setState((d) => ({ ...d, isSubmitting: true }));
    const done = () => {
      this.baseStore.setState((prev) => ({ ...prev, isSubmitting: false }));
    };
    await this.validateAllFields("submit");
    if (!this.state.isFieldsValid) {
      done();
      this.options.onSubmitInvalid?.({
        value: this.state.values,
        formApi: this,
        meta: submitMetaArg
      });
      formEventClient.emit("form-submission", {
        id: this._formId,
        submissionAttempt: this.state.submissionAttempts,
        successful: false,
        stage: "validateAllFields",
        errors: Object.values(this.state.fieldMeta).map((meta) => meta.errors).flat()
      });
      return;
    }
    await this.validate("submit");
    if (!this.state.isValid) {
      done();
      this.options.onSubmitInvalid?.({
        value: this.state.values,
        formApi: this,
        meta: submitMetaArg
      });
      formEventClient.emit("form-submission", {
        id: this._formId,
        submissionAttempt: this.state.submissionAttempts,
        successful: false,
        stage: "validate",
        errors: this.state.errors
      });
      return;
    }
    batch(() => {
      void Object.values(this.fieldInfo).forEach(
        (field) => {
          field.instance?.options.listeners?.onSubmit?.({
            value: field.instance.state.value,
            fieldApi: field.instance
          });
        }
      );
    });
    this.options.listeners?.onSubmit?.({ formApi: this, meta: submitMetaArg });
    try {
      await this.options.onSubmit?.({
        value: this.state.values,
        formApi: this,
        meta: submitMetaArg
      });
      batch(() => {
        this.baseStore.setState((prev) => ({
          ...prev,
          isSubmitted: true,
          isSubmitSuccessful: true
          // Set isSubmitSuccessful to true on successful submission
        }));
        formEventClient.emit("form-submission", {
          id: this._formId,
          submissionAttempt: this.state.submissionAttempts,
          successful: true
        });
        done();
      });
    } catch (err) {
      this.baseStore.setState((prev) => ({
        ...prev,
        isSubmitSuccessful: false
        // Ensure isSubmitSuccessful is false if an error occurs
      }));
      formEventClient.emit("form-submission", {
        id: this._formId,
        submissionAttempt: this.state.submissionAttempts,
        successful: false,
        stage: "inflight",
        onError: err
      });
      done();
      throw err;
    }
  }
  /**
   * Updates the form's errorMap
   */
  setErrorMap(errorMap) {
    batch(() => {
      Object.entries(errorMap).forEach(([key, value]) => {
        const errorMapKey = key;
        if (isGlobalFormValidationError(value)) {
          const { formError, fieldErrors } = normalizeError$1(value);
          for (const fieldName of Object.keys(
            this.fieldInfo
          )) {
            const fieldMeta = this.getFieldMeta(fieldName);
            if (!fieldMeta) continue;
            this.setFieldMeta(fieldName, (prev) => ({
              ...prev,
              errorMap: {
                ...prev.errorMap,
                [errorMapKey]: fieldErrors?.[fieldName]
              },
              errorSourceMap: {
                ...prev.errorSourceMap,
                [errorMapKey]: "form"
              }
            }));
          }
          this.baseStore.setState((prev) => ({
            ...prev,
            errorMap: {
              ...prev.errorMap,
              [errorMapKey]: formError
            }
          }));
        } else {
          this.baseStore.setState((prev) => ({
            ...prev,
            errorMap: {
              ...prev.errorMap,
              [errorMapKey]: value
            }
          }));
        }
      });
    });
  }
}
function normalizeError$1(rawError) {
  if (rawError) {
    if (isGlobalFormValidationError(rawError)) {
      const formError = normalizeError$1(rawError.form).formError;
      const fieldErrors = rawError.fields;
      return { formError, fieldErrors };
    }
    return { formError: rawError };
  }
  return { formError: void 0 };
}
function getErrorMapKey$1(cause) {
  switch (cause) {
    case "submit":
      return "onSubmit";
    case "blur":
      return "onBlur";
    case "mount":
      return "onMount";
    case "server":
      return "onServer";
    case "dynamic":
      return "onDynamic";
    case "change":
    default:
      return "onChange";
  }
}
class FieldApi {
  /**
   * Initializes a new `FieldApi` instance.
   */
  constructor(opts) {
    this.options = {};
    this.mount = () => {
      const cleanup = this.store.mount();
      if (this.options.defaultValue !== void 0) {
        this.form.setFieldValue(this.name, this.options.defaultValue, {
          dontUpdateMeta: true
        });
      }
      const info = this.getInfo();
      info.instance = this;
      this.update(this.options);
      const { onMount } = this.options.validators || {};
      if (onMount) {
        const error = this.runValidator({
          validate: onMount,
          value: {
            value: this.state.value,
            fieldApi: this,
            validationSource: "field"
          },
          type: "validate"
        });
        if (error) {
          this.setMeta(
            (prev) => ({
              ...prev,
              // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
              errorMap: { ...prev?.errorMap, onMount: error },
              errorSourceMap: {
                // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
                ...prev?.errorSourceMap,
                onMount: "field"
              }
            })
          );
        }
      }
      this.options.listeners?.onMount?.({
        value: this.state.value,
        fieldApi: this
      });
      return cleanup;
    };
    this.update = (opts2) => {
      this.options = opts2;
      const nameHasChanged = this.name !== opts2.name;
      this.name = opts2.name;
      if (this.state.value === void 0) {
        const formDefault = getBy(opts2.form.options.defaultValues, opts2.name);
        const defaultValue = opts2.defaultValue ?? formDefault;
        if (nameHasChanged) {
          this.setValue((val) => val || defaultValue, {
            dontUpdateMeta: true
          });
        } else if (defaultValue !== void 0) {
          this.setValue(defaultValue, {
            dontUpdateMeta: true
          });
        }
      }
      if (this.form.getFieldMeta(this.name) === void 0) {
        this.setMeta(this.state.meta);
      }
    };
    this.getValue = () => {
      return this.form.getFieldValue(this.name);
    };
    this.setValue = (updater, options) => {
      this.form.setFieldValue(
        this.name,
        updater,
        mergeOpts(options, { dontRunListeners: true, dontValidate: true })
      );
      if (!options?.dontRunListeners) {
        this.triggerOnChangeListener();
      }
      if (!options?.dontValidate) {
        this.validate("change");
      }
    };
    this.getMeta = () => this.store.state.meta;
    this.setMeta = (updater) => this.form.setFieldMeta(this.name, updater);
    this.getInfo = () => this.form.getFieldInfo(this.name);
    this.pushValue = (value, options) => {
      this.form.pushFieldValue(
        this.name,
        value,
        mergeOpts(options, { dontRunListeners: true })
      );
      if (!options?.dontRunListeners) {
        this.triggerOnChangeListener();
      }
    };
    this.insertValue = (index, value, options) => {
      this.form.insertFieldValue(
        this.name,
        index,
        value,
        mergeOpts(options, { dontRunListeners: true })
      );
      if (!options?.dontRunListeners) {
        this.triggerOnChangeListener();
      }
    };
    this.replaceValue = (index, value, options) => {
      this.form.replaceFieldValue(
        this.name,
        index,
        value,
        mergeOpts(options, { dontRunListeners: true })
      );
      if (!options?.dontRunListeners) {
        this.triggerOnChangeListener();
      }
    };
    this.removeValue = (index, options) => {
      this.form.removeFieldValue(
        this.name,
        index,
        mergeOpts(options, { dontRunListeners: true })
      );
      if (!options?.dontRunListeners) {
        this.triggerOnChangeListener();
      }
    };
    this.swapValues = (aIndex, bIndex, options) => {
      this.form.swapFieldValues(
        this.name,
        aIndex,
        bIndex,
        mergeOpts(options, { dontRunListeners: true })
      );
      if (!options?.dontRunListeners) {
        this.triggerOnChangeListener();
      }
    };
    this.moveValue = (aIndex, bIndex, options) => {
      this.form.moveFieldValues(
        this.name,
        aIndex,
        bIndex,
        mergeOpts(options, { dontRunListeners: true })
      );
      if (!options?.dontRunListeners) {
        this.triggerOnChangeListener();
      }
    };
    this.clearValues = (options) => {
      this.form.clearFieldValues(
        this.name,
        mergeOpts(options, { dontRunListeners: true })
      );
      if (!options?.dontRunListeners) {
        this.triggerOnChangeListener();
      }
    };
    this.getLinkedFields = (cause) => {
      const fields = Object.values(this.form.fieldInfo);
      const linkedFields = [];
      for (const field of fields) {
        if (!field.instance) continue;
        const { onChangeListenTo, onBlurListenTo } = field.instance.options.validators || {};
        if (cause === "change" && onChangeListenTo?.includes(this.name)) {
          linkedFields.push(field.instance);
        }
        if (cause === "blur" && onBlurListenTo?.includes(this.name)) {
          linkedFields.push(field.instance);
        }
      }
      return linkedFields;
    };
    this.validateSync = (cause, errorFromForm) => {
      const validates = getSyncValidatorArray(cause, {
        ...this.options,
        form: this.form,
        validationLogic: this.form.options.validationLogic || defaultValidationLogic
      });
      const linkedFields = this.getLinkedFields(cause);
      const linkedFieldValidates = linkedFields.reduce(
        (acc, field) => {
          const fieldValidates = getSyncValidatorArray(cause, {
            ...field.options,
            form: field.form,
            validationLogic: field.form.options.validationLogic || defaultValidationLogic
          });
          fieldValidates.forEach((validate) => {
            validate.field = field;
          });
          return acc.concat(fieldValidates);
        },
        []
      );
      let hasErrored = false;
      batch(() => {
        const validateFieldFn = (field, validateObj) => {
          const errorMapKey = getErrorMapKey(validateObj.cause);
          const fieldLevelError = validateObj.validate ? normalizeError(
            field.runValidator({
              validate: validateObj.validate,
              value: {
                value: field.store.state.value,
                validationSource: "field",
                fieldApi: field
              },
              type: "validate"
            })
          ) : void 0;
          const formLevelError = errorFromForm[errorMapKey];
          const { newErrorValue, newSource } = determineFieldLevelErrorSourceAndValue({
            formLevelError,
            fieldLevelError
          });
          if (field.state.meta.errorMap?.[errorMapKey] !== newErrorValue) {
            field.setMeta((prev) => ({
              ...prev,
              errorMap: {
                ...prev.errorMap,
                [errorMapKey]: newErrorValue
              },
              errorSourceMap: {
                ...prev.errorSourceMap,
                [errorMapKey]: newSource
              }
            }));
          }
          if (newErrorValue) {
            hasErrored = true;
          }
        };
        for (const validateObj of validates) {
          validateFieldFn(this, validateObj);
        }
        for (const fieldValitateObj of linkedFieldValidates) {
          if (!fieldValitateObj.validate) continue;
          validateFieldFn(fieldValitateObj.field, fieldValitateObj);
        }
      });
      const submitErrKey = getErrorMapKey("submit");
      if (
        // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
        this.state.meta.errorMap?.[submitErrKey] && cause !== "submit" && !hasErrored
      ) {
        this.setMeta((prev) => ({
          ...prev,
          errorMap: {
            ...prev.errorMap,
            [submitErrKey]: void 0
          },
          errorSourceMap: {
            ...prev.errorSourceMap,
            [submitErrKey]: void 0
          }
        }));
      }
      return { hasErrored };
    };
    this.validateAsync = async (cause, formValidationResultPromise) => {
      const validates = getAsyncValidatorArray(cause, {
        ...this.options,
        form: this.form,
        validationLogic: this.form.options.validationLogic || defaultValidationLogic
      });
      const asyncFormValidationResults = await formValidationResultPromise;
      const linkedFields = this.getLinkedFields(cause);
      const linkedFieldValidates = linkedFields.reduce(
        (acc, field) => {
          const fieldValidates = getAsyncValidatorArray(cause, {
            ...field.options,
            form: field.form,
            validationLogic: field.form.options.validationLogic || defaultValidationLogic
          });
          fieldValidates.forEach((validate) => {
            validate.field = field;
          });
          return acc.concat(fieldValidates);
        },
        []
      );
      if (!this.state.meta.isValidating) {
        this.setMeta((prev) => ({ ...prev, isValidating: true }));
      }
      for (const linkedField of linkedFields) {
        linkedField.setMeta((prev) => ({ ...prev, isValidating: true }));
      }
      const validatesPromises = [];
      const linkedPromises = [];
      const validateFieldAsyncFn = (field, validateObj, promises) => {
        const errorMapKey = getErrorMapKey(validateObj.cause);
        const fieldValidatorMeta = field.getInfo().validationMetaMap[errorMapKey];
        fieldValidatorMeta?.lastAbortController.abort();
        const controller = new AbortController();
        this.getInfo().validationMetaMap[errorMapKey] = {
          lastAbortController: controller
        };
        promises.push(
          new Promise(async (resolve) => {
            let rawError;
            try {
              rawError = await new Promise((rawResolve, rawReject) => {
                if (this.timeoutIds.validations[validateObj.cause]) {
                  clearTimeout(this.timeoutIds.validations[validateObj.cause]);
                }
                this.timeoutIds.validations[validateObj.cause] = setTimeout(
                  async () => {
                    if (controller.signal.aborted) return rawResolve(void 0);
                    try {
                      rawResolve(
                        await this.runValidator({
                          validate: validateObj.validate,
                          value: {
                            value: field.store.state.value,
                            fieldApi: field,
                            signal: controller.signal,
                            validationSource: "field"
                          },
                          type: "validateAsync"
                        })
                      );
                    } catch (e) {
                      rawReject(e);
                    }
                  },
                  validateObj.debounceMs
                );
              });
            } catch (e) {
              rawError = e;
            }
            if (controller.signal.aborted) return resolve(void 0);
            const fieldLevelError = normalizeError(rawError);
            const formLevelError = asyncFormValidationResults[this.name]?.[errorMapKey];
            const { newErrorValue, newSource } = determineFieldLevelErrorSourceAndValue({
              formLevelError,
              fieldLevelError
            });
            field.setMeta((prev) => {
              return {
                ...prev,
                errorMap: {
                  // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
                  ...prev?.errorMap,
                  [errorMapKey]: newErrorValue
                },
                errorSourceMap: {
                  ...prev.errorSourceMap,
                  [errorMapKey]: newSource
                }
              };
            });
            resolve(newErrorValue);
          })
        );
      };
      for (const validateObj of validates) {
        if (!validateObj.validate) continue;
        validateFieldAsyncFn(this, validateObj, validatesPromises);
      }
      for (const fieldValitateObj of linkedFieldValidates) {
        if (!fieldValitateObj.validate) continue;
        validateFieldAsyncFn(
          fieldValitateObj.field,
          fieldValitateObj,
          linkedPromises
        );
      }
      let results = [];
      if (validatesPromises.length || linkedPromises.length) {
        results = await Promise.all(validatesPromises);
        await Promise.all(linkedPromises);
      }
      this.setMeta((prev) => ({ ...prev, isValidating: false }));
      for (const linkedField of linkedFields) {
        linkedField.setMeta((prev) => ({ ...prev, isValidating: false }));
      }
      return results.filter(Boolean);
    };
    this.validate = (cause, opts2) => {
      if (!this.state.meta.isTouched) return [];
      const { fieldsErrorMap } = opts2?.skipFormValidation ? { fieldsErrorMap: {} } : this.form.validateSync(cause);
      const { hasErrored } = this.validateSync(
        cause,
        fieldsErrorMap[this.name] ?? {}
      );
      if (hasErrored && !this.options.asyncAlways) {
        this.getInfo().validationMetaMap[getErrorMapKey(cause)]?.lastAbortController.abort();
        return this.state.meta.errors;
      }
      const formValidationResultPromise = opts2?.skipFormValidation ? Promise.resolve({}) : this.form.validateAsync(cause);
      return this.validateAsync(cause, formValidationResultPromise);
    };
    this.handleChange = (updater) => {
      this.setValue(updater);
    };
    this.handleBlur = () => {
      const prevTouched = this.state.meta.isTouched;
      if (!prevTouched) {
        this.setMeta((prev) => ({ ...prev, isTouched: true }));
      }
      if (!this.state.meta.isBlurred) {
        this.setMeta((prev) => ({ ...prev, isBlurred: true }));
      }
      this.validate("blur");
      this.triggerOnBlurListener();
    };
    this.parseValueWithSchema = (schema) => {
      return standardSchemaValidators.validate(
        { value: this.state.value, validationSource: "field" },
        schema
      );
    };
    this.parseValueWithSchemaAsync = (schema) => {
      return standardSchemaValidators.validateAsync(
        { value: this.state.value, validationSource: "field" },
        schema
      );
    };
    this.form = opts.form;
    this.name = opts.name;
    this.timeoutIds = {
      validations: {},
      listeners: {},
      formListeners: {}
    };
    this.store = new Derived({
      deps: [this.form.store],
      fn: () => {
        const value = this.form.getFieldValue(this.name);
        const meta = this.form.getFieldMeta(this.name) ?? {
          ...defaultFieldMeta,
          ...opts.defaultMeta
        };
        return {
          value,
          meta
        };
      }
    });
    this.options = opts;
  }
  /**
   * The current field state.
   */
  get state() {
    return this.store.state;
  }
  /**
   * @private
   */
  runValidator(props) {
    if (isStandardSchemaValidator(props.validate)) {
      return standardSchemaValidators[props.type](
        props.value,
        props.validate
      );
    }
    return props.validate(props.value);
  }
  /**
   * Updates the field's errorMap
   */
  setErrorMap(errorMap) {
    this.setMeta((prev) => ({
      ...prev,
      errorMap: {
        ...prev.errorMap,
        ...errorMap
      }
    }));
  }
  triggerOnBlurListener() {
    const formDebounceMs = this.form.options.listeners?.onBlurDebounceMs;
    if (formDebounceMs && formDebounceMs > 0) {
      if (this.timeoutIds.formListeners.blur) {
        clearTimeout(this.timeoutIds.formListeners.blur);
      }
      this.timeoutIds.formListeners.blur = setTimeout(() => {
        this.form.options.listeners?.onBlur?.({
          formApi: this.form,
          fieldApi: this
        });
      }, formDebounceMs);
    } else {
      this.form.options.listeners?.onBlur?.({
        formApi: this.form,
        fieldApi: this
      });
    }
    const fieldDebounceMs = this.options.listeners?.onBlurDebounceMs;
    if (fieldDebounceMs && fieldDebounceMs > 0) {
      if (this.timeoutIds.listeners.blur) {
        clearTimeout(this.timeoutIds.listeners.blur);
      }
      this.timeoutIds.listeners.blur = setTimeout(() => {
        this.options.listeners?.onBlur?.({
          value: this.state.value,
          fieldApi: this
        });
      }, fieldDebounceMs);
    } else {
      this.options.listeners?.onBlur?.({
        value: this.state.value,
        fieldApi: this
      });
    }
  }
  /**
   * @private
   */
  triggerOnChangeListener() {
    const formDebounceMs = this.form.options.listeners?.onChangeDebounceMs;
    if (formDebounceMs && formDebounceMs > 0) {
      if (this.timeoutIds.formListeners.change) {
        clearTimeout(this.timeoutIds.formListeners.change);
      }
      this.timeoutIds.formListeners.change = setTimeout(() => {
        this.form.options.listeners?.onChange?.({
          formApi: this.form,
          fieldApi: this
        });
      }, formDebounceMs);
    } else {
      this.form.options.listeners?.onChange?.({
        formApi: this.form,
        fieldApi: this
      });
    }
    const fieldDebounceMs = this.options.listeners?.onChangeDebounceMs;
    if (fieldDebounceMs && fieldDebounceMs > 0) {
      if (this.timeoutIds.listeners.change) {
        clearTimeout(this.timeoutIds.listeners.change);
      }
      this.timeoutIds.listeners.change = setTimeout(() => {
        this.options.listeners?.onChange?.({
          value: this.state.value,
          fieldApi: this
        });
      }, fieldDebounceMs);
    } else {
      this.options.listeners?.onChange?.({
        value: this.state.value,
        fieldApi: this
      });
    }
  }
}
function normalizeError(rawError) {
  if (rawError) {
    return rawError;
  }
  return void 0;
}
function getErrorMapKey(cause) {
  switch (cause) {
    case "submit":
      return "onSubmit";
    case "blur":
      return "onBlur";
    case "mount":
      return "onMount";
    case "server":
      return "onServer";
    case "dynamic":
      return "onDynamic";
    case "change":
    default:
      return "onChange";
  }
}
class FieldGroupApi {
  /**
   * Constructs a new `FieldGroupApi` instance with the given form options.
   */
  constructor(opts) {
    this.getFormFieldName = (subfield) => {
      if (typeof this.fieldsMap === "string") {
        return concatenatePaths(this.fieldsMap, subfield);
      }
      const firstAccessor = makePathArray(subfield)[0];
      if (typeof firstAccessor !== "string") {
        return "";
      }
      const restOfPath = subfield.slice(firstAccessor.length);
      const formMappedPath = (
        // TFields is either a string or this. See guard above.
        this.fieldsMap[firstAccessor]
      );
      return concatenatePaths(formMappedPath, restOfPath);
    };
    this.getFormFieldOptions = (props) => {
      const newProps = { ...props };
      const validators = newProps.validators;
      newProps.name = this.getFormFieldName(props.name);
      if (validators && (validators.onChangeListenTo || validators.onBlurListenTo)) {
        const newValidators = { ...validators };
        const remapListenTo = (listenTo) => {
          if (!listenTo) return void 0;
          return listenTo.map(
            (localFieldName) => this.getFormFieldName(localFieldName)
          );
        };
        newValidators.onChangeListenTo = remapListenTo(
          validators.onChangeListenTo
        );
        newValidators.onBlurListenTo = remapListenTo(validators.onBlurListenTo);
        newProps.validators = newValidators;
      }
      return newProps;
    };
    this.mount = () => {
      const cleanup = this.store.mount();
      return cleanup;
    };
    this.validateArrayFieldsStartingFrom = async (field, index, cause) => {
      return this.form.validateArrayFieldsStartingFrom(
        this.getFormFieldName(field),
        index,
        cause
      );
    };
    this.validateField = (field, cause) => {
      return this.form.validateField(this.getFormFieldName(field), cause);
    };
    this.getFieldValue = (field) => {
      return this.form.getFieldValue(this.getFormFieldName(field));
    };
    this.getFieldMeta = (field) => {
      return this.form.getFieldMeta(this.getFormFieldName(field));
    };
    this.setFieldMeta = (field, updater) => {
      return this.form.setFieldMeta(this.getFormFieldName(field), updater);
    };
    this.setFieldValue = (field, updater, opts2) => {
      return this.form.setFieldValue(
        this.getFormFieldName(field),
        updater,
        opts2
      );
    };
    this.deleteField = (field) => {
      return this.form.deleteField(this.getFormFieldName(field));
    };
    this.pushFieldValue = (field, value, opts2) => {
      return this.form.pushFieldValue(
        this.getFormFieldName(field),
        // since unknown doesn't extend an array, it types `value` as never.
        value,
        opts2
      );
    };
    this.insertFieldValue = async (field, index, value, opts2) => {
      return this.form.insertFieldValue(
        this.getFormFieldName(field),
        index,
        // since unknown doesn't extend an array, it types `value` as never.
        value,
        opts2
      );
    };
    this.replaceFieldValue = async (field, index, value, opts2) => {
      return this.form.replaceFieldValue(
        this.getFormFieldName(field),
        index,
        // since unknown doesn't extend an array, it types `value` as never.
        value,
        opts2
      );
    };
    this.removeFieldValue = async (field, index, opts2) => {
      return this.form.removeFieldValue(this.getFormFieldName(field), index, opts2);
    };
    this.swapFieldValues = (field, index1, index2, opts2) => {
      return this.form.swapFieldValues(
        this.getFormFieldName(field),
        index1,
        index2,
        opts2
      );
    };
    this.moveFieldValues = (field, index1, index2, opts2) => {
      return this.form.moveFieldValues(
        this.getFormFieldName(field),
        index1,
        index2,
        opts2
      );
    };
    this.clearFieldValues = (field, opts2) => {
      return this.form.clearFieldValues(this.getFormFieldName(field), opts2);
    };
    this.resetField = (field) => {
      return this.form.resetField(this.getFormFieldName(field));
    };
    this.validateAllFields = (cause) => this.form.validateAllFields(cause);
    if (opts.form instanceof FieldGroupApi) {
      const group = opts.form;
      this.form = group.form;
      if (typeof opts.fields === "string") {
        this.fieldsMap = group.getFormFieldName(opts.fields);
      } else {
        const fields = {
          ...opts.fields
        };
        for (const key in fields) {
          fields[key] = group.getFormFieldName(fields[key]);
        }
        this.fieldsMap = fields;
      }
    } else {
      this.form = opts.form;
      this.fieldsMap = opts.fields;
    }
    this.store = new Derived({
      deps: [this.form.store],
      fn: ({ currDepVals }) => {
        const currFormStore = currDepVals[0];
        let values;
        if (typeof this.fieldsMap === "string") {
          values = getBy(currFormStore.values, this.fieldsMap);
        } else {
          values = {};
          const fields = this.fieldsMap;
          for (const key in fields) {
            values[key] = getBy(currFormStore.values, fields[key]);
          }
        }
        return {
          values
        };
      }
    });
  }
  get state() {
    return this.store.state;
  }
  async handleSubmit(submitMeta) {
    return this.form.handleSubmit(submitMeta);
  }
}
function useStore(store, selector = (d) => d) {
  const slice = withSelectorExports.useSyncExternalStoreWithSelector(
    store.subscribe,
    () => store.state,
    () => store.state,
    selector,
    shallow
  );
  return slice;
}
function shallow(objA, objB) {
  if (Object.is(objA, objB)) {
    return true;
  }
  if (typeof objA !== "object" || objA === null || typeof objB !== "object" || objB === null) {
    return false;
  }
  if (objA instanceof Map && objB instanceof Map) {
    if (objA.size !== objB.size) return false;
    for (const [k, v] of objA) {
      if (!objB.has(k) || !Object.is(v, objB.get(k))) return false;
    }
    return true;
  }
  if (objA instanceof Set && objB instanceof Set) {
    if (objA.size !== objB.size) return false;
    for (const v of objA) {
      if (!objB.has(v)) return false;
    }
    return true;
  }
  if (objA instanceof Date && objB instanceof Date) {
    if (objA.getTime() !== objB.getTime()) return false;
    return true;
  }
  const keysA = getOwnKeys(objA);
  if (keysA.length !== getOwnKeys(objB).length) {
    return false;
  }
  for (let i = 0; i < keysA.length; i++) {
    if (!Object.prototype.hasOwnProperty.call(objB, keysA[i]) || !Object.is(objA[keysA[i]], objB[keysA[i]])) {
      return false;
    }
  }
  return true;
}
function getOwnKeys(obj) {
  return Object.keys(obj).concat(
    Object.getOwnPropertySymbols(obj)
  );
}
const useIsomorphicLayoutEffect = typeof window !== "undefined" ? reactExports.useLayoutEffect : reactExports.useEffect;
function useField(opts) {
  const [fieldApi] = reactExports.useState(() => {
    const api = new FieldApi({
      ...opts,
      form: opts.form,
      name: opts.name
    });
    const extendedApi = api;
    extendedApi.Field = Field;
    return extendedApi;
  });
  useIsomorphicLayoutEffect(fieldApi.mount, [fieldApi]);
  useIsomorphicLayoutEffect(() => {
    fieldApi.update(opts);
  });
  useStore(
    fieldApi.store,
    opts.mode === "array" ? (state) => {
      return [
        state.meta,
        Object.keys(state.value ?? []).length
      ];
    } : void 0
  );
  return fieldApi;
}
const Field = (({
  children,
  ...fieldOptions
}) => {
  const fieldApi = useField(fieldOptions);
  const jsxToDisplay = reactExports.useMemo(
    () => functionalUpdate(children, fieldApi),
    /**
     * The reason this exists is to fix an issue with the React Compiler.
     * Namely, functionalUpdate is memoized where it checks for `fieldApi`, which is a static type.
     * This means that when `state.value` changes, it does not trigger a re-render. The useMemo explicitly fixes this problem
     */
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [children, fieldApi, fieldApi.state.value, fieldApi.state.meta]
  );
  return /* @__PURE__ */ jsxRuntimeExports.jsx(jsxRuntimeExports.Fragment, { children: jsxToDisplay });
});
function LocalSubscribe$1({
  form,
  selector,
  children
}) {
  const data = useStore(form.store, selector);
  return functionalUpdate(children, data);
}
function useForm(opts) {
  const formId = reactExports.useId();
  const [formApi] = reactExports.useState(() => {
    const api = new FormApi({ ...opts, formId });
    const extendedApi = api;
    extendedApi.Field = function APIField(props) {
      return /* @__PURE__ */ jsxRuntimeExports.jsx(Field, { ...props, form: api });
    };
    extendedApi.Subscribe = function Subscribe(props) {
      return /* @__PURE__ */ jsxRuntimeExports.jsx(
        LocalSubscribe$1,
        {
          form: api,
          selector: props.selector,
          children: props.children
        }
      );
    };
    return extendedApi;
  });
  useIsomorphicLayoutEffect(formApi.mount, []);
  useIsomorphicLayoutEffect(() => {
    formApi.update(opts);
  });
  return formApi;
}
function LocalSubscribe({
  lens,
  selector,
  children
}) {
  const data = useStore(lens.store, selector);
  return functionalUpdate(children, data);
}
function useFieldGroup(opts) {
  const [formLensApi] = reactExports.useState(() => {
    const api = new FieldGroupApi(opts);
    const form = opts.form instanceof FieldGroupApi ? opts.form.form : opts.form;
    const extendedApi = api;
    extendedApi.AppForm = function AppForm(appFormProps) {
      return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppForm, { ...appFormProps });
    };
    extendedApi.AppField = function AppField(props) {
      return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppField, { ...formLensApi.getFormFieldOptions(props) });
    };
    extendedApi.Field = function Field2(props) {
      return /* @__PURE__ */ jsxRuntimeExports.jsx(form.Field, { ...formLensApi.getFormFieldOptions(props) });
    };
    extendedApi.Subscribe = function Subscribe(props) {
      return /* @__PURE__ */ jsxRuntimeExports.jsx(
        LocalSubscribe,
        {
          lens: formLensApi,
          selector: props.selector,
          children: props.children
        }
      );
    };
    return Object.assign(extendedApi, {
      ...opts.formComponents
    });
  });
  useIsomorphicLayoutEffect(formLensApi.mount, [formLensApi]);
  return formLensApi;
}
const fieldContext$1 = reactExports.createContext(null);
const formContext$1 = reactExports.createContext(null);
function createFormHookContexts() {
  function useFieldContext2() {
    const field = reactExports.useContext(fieldContext$1);
    if (!field) {
      throw new Error(
        "`fieldContext` only works when within a `fieldComponent` passed to `createFormHook`"
      );
    }
    return field;
  }
  function useFormContext2() {
    const form = reactExports.useContext(formContext$1);
    if (!form) {
      throw new Error(
        "`formContext` only works when within a `formComponent` passed to `createFormHook`"
      );
    }
    return form;
  }
  return { fieldContext: fieldContext$1, useFieldContext: useFieldContext2, useFormContext: useFormContext2, formContext: formContext$1 };
}
function createFormHook({
  fieldComponents,
  fieldContext: fieldContext2,
  formContext: formContext2,
  formComponents
}) {
  function useAppForm2(props) {
    const form = useForm(props);
    const AppForm = reactExports.useMemo(() => {
      const AppForm2 = (({ children }) => {
        return /* @__PURE__ */ jsxRuntimeExports.jsx(formContext2.Provider, { value: form, children });
      });
      return AppForm2;
    }, [form]);
    const AppField = reactExports.useMemo(() => {
      const AppField2 = (({ children, ...props2 }) => {
        return /* @__PURE__ */ jsxRuntimeExports.jsx(form.Field, { ...props2, children: (field) => (
          // eslint-disable-next-line @eslint-react/no-context-provider
          /* @__PURE__ */ jsxRuntimeExports.jsx(fieldContext2.Provider, { value: field, children: children(Object.assign(field, fieldComponents)) })
        ) });
      });
      return AppField2;
    }, [form]);
    const extendedForm = reactExports.useMemo(() => {
      return Object.assign(form, {
        AppField,
        AppForm,
        ...formComponents
      });
    }, [form, AppField, AppForm]);
    return extendedForm;
  }
  function withForm2({
    render,
    props
  }) {
    return (innerProps) => render({ ...props, ...innerProps });
  }
  function withFieldGroup({
    render,
    props,
    defaultValues
  }) {
    return function Render(innerProps) {
      const fieldGroupProps = reactExports.useMemo(() => {
        return {
          form: innerProps.form,
          fields: innerProps.fields,
          defaultValues,
          formComponents
        };
      }, [innerProps.form, innerProps.fields]);
      const fieldGroupApi = useFieldGroup(fieldGroupProps);
      return render({ ...props, ...innerProps, group: fieldGroupApi });
    };
  }
  return {
    useAppForm: useAppForm2,
    withForm: withForm2,
    withFieldGroup
  };
}
const { fieldContext, useFieldContext, formContext, useFormContext } = createFormHookContexts();
const FormActions = () => {
  const form = useFormContext();
  return /* @__PURE__ */ jsxRuntimeExports.jsx(form.Subscribe, { selector: (state) => state.isDirty, children: (isDirty) => /* @__PURE__ */ jsxRuntimeExports.jsxs("div", { className: "flex items-center justify-end gap-x-3 sticky bottom-0 bg-background p-3 z-50 mt-2", children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx(
      Button,
      {
        disabled: !isDirty || form.state.isSubmitting,
        type: "reset",
        variant: "secondary",
        onClick: () => form.reset(),
        children: __("Reset", "wp-sms")
      }
    ),
    /* @__PURE__ */ jsxRuntimeExports.jsxs(Button, { disabled: !isDirty || form.state.isSubmitting, type: "submit", children: [
      /* @__PURE__ */ jsxRuntimeExports.jsx(Save, {}),
      __("Save Changes", "wp-sms")
    ] })
  ] }) });
};
const CheckboxField = reactExports.lazy(
  () => __vitePreload(() => import("./checkbox-field-C7DnLjl-.js"), true ? __vite__mapDeps([0,1,2,3,4,5,6,7,8]) : void 0, import.meta.url).then((m) => ({ default: m.CheckboxField }))
);
const ColorField = reactExports.lazy(() => __vitePreload(() => import("./color-field-BKwHJvXz.js"), true ? __vite__mapDeps([9,1,2,10,6,7,8,3]) : void 0, import.meta.url).then((m) => ({ default: m.ColorField })));
const Header = reactExports.lazy(() => __vitePreload(() => import("./display-fields-YdWYTNhr.js"), true ? __vite__mapDeps([11,1,2,7,12,6,8,3]) : void 0, import.meta.url).then((m) => ({ default: m.Header })));
const HtmlRenderer = reactExports.lazy(
  () => __vitePreload(() => import("./display-fields-YdWYTNhr.js"), true ? __vite__mapDeps([11,1,2,7,12,6,8,3]) : void 0, import.meta.url).then((m) => ({ default: m.HtmlRenderer }))
);
const Notice = reactExports.lazy(() => __vitePreload(() => import("./display-fields-YdWYTNhr.js"), true ? __vite__mapDeps([11,1,2,7,12,6,8,3]) : void 0, import.meta.url).then((m) => ({ default: m.Notice })));
const ImageField = reactExports.lazy(() => __vitePreload(() => import("./image-field-Bqxum0mJ.js"), true ? __vite__mapDeps([13,1,2,6,7,8,3]) : void 0, import.meta.url).then((m) => ({ default: m.ImageField })));
const MultiselectField = reactExports.lazy(
  () => __vitePreload(() => import("./multiselect-field-CpVm0hZz.js"), true ? __vite__mapDeps([14,1,2,8,15,3,6,16,4,12,17,18,5,7]) : void 0, import.meta.url).then((m) => ({ default: m.MultiselectField }))
);
const NumberField = reactExports.lazy(
  () => __vitePreload(() => import("./number-field-D5-9jmzd.js"), true ? __vite__mapDeps([19,1,2,10,6,7,8,3]) : void 0, import.meta.url).then((m) => ({ default: m.NumberField }))
);
const RepeaterField = reactExports.lazy(
  () => __vitePreload(() => import("./repeater-field-ATMtFajw.js"), true ? __vite__mapDeps([20,1,2,8,6,7,3]) : void 0, import.meta.url).then((m) => ({ default: m.RepeaterField }))
);
const SelectField = reactExports.lazy(
  () => __vitePreload(() => import("./select-field-8ejDK4NW.js"), true ? __vite__mapDeps([21,1,2,15,3,6,16,4,18,5,7,8]) : void 0, import.meta.url).then((m) => ({ default: m.SelectField }))
);
const TelField = reactExports.lazy(() => __vitePreload(() => import("./tel-field-DF15XWvu.js"), true ? __vite__mapDeps([22,1,2,15,3,6,16,4,10,5,7,8]) : void 0, import.meta.url).then((m) => ({ default: m.TelField })));
const TextField = reactExports.lazy(() => __vitePreload(() => import("./text-field-Dc3Q4jKb.js"), true ? __vite__mapDeps([23,1,2,10,6,7,8,3]) : void 0, import.meta.url).then((m) => ({ default: m.TextField })));
const TextareaField = reactExports.lazy(
  () => __vitePreload(() => import("./textarea-field-CdfmBeFQ.js"), true ? __vite__mapDeps([24,1,2,6,7,8,3]) : void 0, import.meta.url).then((m) => ({ default: m.TextareaField }))
);
const PasswordField = reactExports.lazy(
  () => __vitePreload(() => import("./password-field-BwXNaEmX.js"), true ? __vite__mapDeps([25,1,2,10,6,7,8,3]) : void 0, import.meta.url).then((m) => ({ default: m.PasswordField }))
);
const { useAppForm, withForm } = createFormHook({
  fieldComponents: {
    CheckboxField,
    ColorField,
    ImageField,
    MultiselectField,
    NumberField,
    RepeaterField,
    SelectField,
    TelField,
    TextField,
    TextareaField,
    HtmlRenderer,
    Header,
    Notice,
    PasswordField
  },
  formComponents: {
    FormActions
  },
  fieldContext,
  formContext
});
const getDirtyFormValues = (form, schema) => {
  if (!schema?.sections) {
    return {};
  }
  const collectAllFieldKeys = (fields = []) => {
    return fields.flatMap((field) => [field.key, ...collectAllFieldKeys(field.sub_fields || [])]);
  };
  const allFieldKeys = schema.sections.flatMap((section) => collectAllFieldKeys(section.fields || []));
  const dirtyFieldNames = allFieldKeys.filter((key) => Boolean(form.getFieldMeta?.(key)?.isDirty));
  return dirtyFieldNames.reduce((acc, key) => {
    acc[key] = form.getFieldValue(key);
    return acc;
  }, {});
};
const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
  {
    variants: {
      variant: {
        default: "border-transparent bg-primary text-primary-foreground [a&]:hover:bg-primary/90",
        secondary: "border-transparent bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90",
        destructive: "border-transparent bg-destructive text-white [a&]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60",
        outline: "text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground"
      }
    },
    defaultVariants: {
      variant: "default"
    }
  }
);
function Badge({
  className,
  variant,
  asChild = false,
  ...props
}) {
  const Comp = asChild ? Slot : "span";
  return /* @__PURE__ */ jsxRuntimeExports.jsx(Comp, { "data-slot": "badge", className: cn(badgeVariants({ variant }), className), ...props });
}
const tagConfig = {
  new: { label: __("New", "wp-sms"), color: "bg-green-100 text-green-800", icon: Sparkles },
  deprecated: { label: __("Deprecated", "wp-sms"), color: "bg-red-100 text-red-800", icon: TriangleAlert },
  beta: { label: __("Beta", "wp-sms"), color: "bg-yellow-100 text-yellow-800", icon: Beaker },
  pro: { label: __("Pro", "wp-sms"), color: "bg-purple-100 text-purple-800", icon: Crown },
  experimental: { label: __("Experimental", "wp-sms"), color: "bg-orange-100 text-orange-800", icon: TestTube },
  "coming-soon": { label: __("Coming Soon", "wp-sms"), color: "bg-blue-100 text-blue-800", icon: Clock }
};
function TagBadge({ tag, className = "" }) {
  const tagInfo = tagConfig[tag];
  if (!tagInfo) {
    return null;
  }
  const TagIcon = tagInfo.icon;
  return /* @__PURE__ */ jsxRuntimeExports.jsxs(Badge, { variant: "secondary", className: `${tagInfo.color} ${className}`, children: [
    TagIcon && /* @__PURE__ */ jsxRuntimeExports.jsx(TagIcon, { className: "w-3 h-3 mr-1" }),
    tagInfo.label
  ] });
}
const normalizeValue = (value) => {
  if (typeof value === "boolean") return value;
  if (value === "true") return true;
  if (value === "false") return false;
  if (value === "1") return true;
  if (value === "0") return false;
  return value;
};
const shouldShowField = (schema, formValues) => {
  if (schema.hidden) return false;
  const showIfEntries = Object.entries(schema.showIf ?? {});
  const hideIfEntries = Object.entries(schema.hideIf ?? {});
  const shouldShowCondition = showIfEntries.length === 0 ? true : showIfEntries.every(([key, expectedValue]) => {
    const actualValue = formValues[key];
    return normalizeValue(actualValue) === normalizeValue(expectedValue);
  });
  const shouldHideCondition = hideIfEntries.some(([key, expectedValue]) => {
    const actualValue = formValues[key];
    return normalizeValue(actualValue) === normalizeValue(expectedValue);
  });
  return shouldShowCondition && !shouldHideCondition;
};
const AutoSaveWrapper = ({ form, schema, onSubmit, children }) => {
  const autoSaveTimeout = reactExports.useRef(null);
  const fieldValue = useStore(form.baseStore, (state) => state.values[schema.key]);
  const previousValue = reactExports.useRef(fieldValue);
  const handleAutoSave = reactExports.useCallback(async () => {
    if (!onSubmit || !schema.auto_save_and_refresh) {
      return;
    }
    try {
      const autoSaveData = { [schema.key]: fieldValue };
      await onSubmit(autoSaveData);
    } catch (error) {
      console.error("Auto-save failed:", error);
    }
  }, [onSubmit, schema.auto_save_and_refresh, schema.key, fieldValue]);
  reactExports.useEffect(() => {
    if (!schema.auto_save_and_refresh || fieldValue === previousValue.current) {
      return;
    }
    if (autoSaveTimeout.current) {
      clearTimeout(autoSaveTimeout.current);
    }
    autoSaveTimeout.current = setTimeout(() => {
      handleAutoSave();
    }, 500);
    previousValue.current = fieldValue;
    return () => {
      if (autoSaveTimeout.current) {
        clearTimeout(autoSaveTimeout.current);
      }
    };
  }, [fieldValue, schema.auto_save_and_refresh, handleAutoSave]);
  return /* @__PURE__ */ jsxRuntimeExports.jsx(jsxRuntimeExports.Fragment, { children });
};
const ConditionalRenderer = ({ form, schema, children }) => {
  const dependentFields = reactExports.useMemo(() => {
    const showIfKeys = Object.keys(schema.showIf ?? {});
    const hideIfKeys = Object.keys(schema.hideIf ?? {});
    return [...showIfKeys, ...hideIfKeys];
  }, [schema.showIf, schema.hideIf]);
  const shouldSubscribe = dependentFields.length > 0;
  const formValues = useStore(form.baseStore, (state) => {
    if (!shouldSubscribe) return {};
    const values = state.values;
    return dependentFields.reduce(
      (acc, key) => {
        acc[key] = values[key];
        return acc;
      },
      {}
    );
  });
  const shouldShow = reactExports.useMemo(() => {
    const allFormValues = shouldSubscribe ? formValues : form.baseStore.state.values;
    return shouldShowField(schema, allFormValues);
  }, [shouldSubscribe, formValues, schema, form]);
  if (!shouldShow) return null;
  return /* @__PURE__ */ jsxRuntimeExports.jsx(jsxRuntimeExports.Fragment, { children });
};
const FieldRenderer = withForm({
  props: {
    schema: {},
    onOpenSubFields: () => {
    },
    onSubmit: async () => {
    }
  },
  render: ({ form, ...props }) => {
    const { schema, onOpenSubFields, onSubmit } = props;
    const subFields = schema.sub_fields || [];
    const hasSubFields = subFields.length > 0;
    const renderFieldContent = () => {
      switch (schema.type) {
        case "text":
          return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppField, { name: schema.key, children: (field) => /* @__PURE__ */ jsxRuntimeExports.jsx(field.TextField, { schema }) });
        case "textarea":
          return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppField, { name: schema.key, children: (field) => /* @__PURE__ */ jsxRuntimeExports.jsx(field.TextareaField, { schema }) });
        case "number":
          return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppField, { name: schema.key, children: (field) => /* @__PURE__ */ jsxRuntimeExports.jsx(field.NumberField, { schema }) });
        case "select":
        case "advancedselect":
        case "countryselect":
          return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppField, { name: schema.key, children: (field) => /* @__PURE__ */ jsxRuntimeExports.jsx(field.SelectField, { schema }) });
        case "multiselect":
          return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppField, { name: schema.key, children: (field) => /* @__PURE__ */ jsxRuntimeExports.jsx(field.MultiselectField, { schema }) });
        case "checkbox":
          return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppField, { name: schema.key, children: (field) => /* @__PURE__ */ jsxRuntimeExports.jsx(field.CheckboxField, { schema }) });
        case "html":
          return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppField, { name: schema.key, children: (field) => /* @__PURE__ */ jsxRuntimeExports.jsx(field.HtmlRenderer, { schema }) });
        case "header":
          return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppField, { name: schema.key, children: (field) => /* @__PURE__ */ jsxRuntimeExports.jsx(field.Header, { schema }) });
        case "color":
          return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppField, { name: schema.key, children: (field) => /* @__PURE__ */ jsxRuntimeExports.jsx(field.ColorField, { schema }) });
        case "notice":
          return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppField, { name: schema.key, children: (field) => /* @__PURE__ */ jsxRuntimeExports.jsx(field.Notice, { schema }) });
        case "repeater":
          return /* @__PURE__ */ jsxRuntimeExports.jsx(
            form.AppField,
            {
              name: schema.key,
              children: (field) => /* @__PURE__ */ jsxRuntimeExports.jsx(field.RepeaterField, { schema, form })
            }
          );
        case "tel":
          return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppField, { name: schema.key, children: (field) => /* @__PURE__ */ jsxRuntimeExports.jsx(field.TelField, { schema }) });
        case "image":
          return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppField, { name: schema.key, children: (field) => /* @__PURE__ */ jsxRuntimeExports.jsx(field.ImageField, { schema }) });
        case "password":
          return /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppField, { name: schema.key, children: (field) => /* @__PURE__ */ jsxRuntimeExports.jsx(field.PasswordField, { schema }) });
        default:
          return /* @__PURE__ */ jsxRuntimeExports.jsx("div", { children: sprintf(__("Unsupported field type: %s", "wp-sms"), schema.type) });
      }
    };
    return /* @__PURE__ */ jsxRuntimeExports.jsx(ConditionalRenderer, { form, schema, children: /* @__PURE__ */ jsxRuntimeExports.jsx(AutoSaveWrapper, { form, schema, onSubmit, children: /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "flex items-center gap-2", children: /* @__PURE__ */ jsxRuntimeExports.jsxs(reactExports.Suspense, { children: [
      /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "flex-1", children: renderFieldContent() }),
      hasSubFields && onOpenSubFields && /* @__PURE__ */ jsxRuntimeExports.jsx(
        Button,
        {
          type: "button",
          variant: "ghost",
          size: "sm",
          onClick: () => onOpenSubFields(schema),
          className: "h-8 w-8 p-0 text-muted-foreground hover:text-foreground",
          children: /* @__PURE__ */ jsxRuntimeExports.jsx(Settings, {})
        }
      )
    ] }) }) }) });
  }
});
var ROOT_NAME = "AlertDialog";
var [createAlertDialogContext] = createContextScope(ROOT_NAME, [
  createDialogScope
]);
var useDialogScope = createDialogScope();
var AlertDialog$1 = (props) => {
  const { __scopeAlertDialog, ...alertDialogProps } = props;
  const dialogScope = useDialogScope(__scopeAlertDialog);
  return /* @__PURE__ */ jsxRuntimeExports.jsx(Root, { ...dialogScope, ...alertDialogProps, modal: true });
};
AlertDialog$1.displayName = ROOT_NAME;
var TRIGGER_NAME = "AlertDialogTrigger";
var AlertDialogTrigger$1 = reactExports.forwardRef(
  (props, forwardedRef) => {
    const { __scopeAlertDialog, ...triggerProps } = props;
    const dialogScope = useDialogScope(__scopeAlertDialog);
    return /* @__PURE__ */ jsxRuntimeExports.jsx(Trigger, { ...dialogScope, ...triggerProps, ref: forwardedRef });
  }
);
AlertDialogTrigger$1.displayName = TRIGGER_NAME;
var OVERLAY_NAME = "AlertDialogOverlay";
var AlertDialogOverlay$1 = reactExports.forwardRef(
  (props, forwardedRef) => {
    const { __scopeAlertDialog, ...overlayProps } = props;
    const dialogScope = useDialogScope(__scopeAlertDialog);
    return /* @__PURE__ */ jsxRuntimeExports.jsx(Overlay, { ...dialogScope, ...overlayProps, ref: forwardedRef });
  }
);
AlertDialogOverlay$1.displayName = OVERLAY_NAME;
var CONTENT_NAME = "AlertDialogContent";
var [AlertDialogContentProvider, useAlertDialogContentContext] = createAlertDialogContext(CONTENT_NAME);
var Slottable = createSlottable("AlertDialogContent");
var AlertDialogContent$1 = reactExports.forwardRef(
  (props, forwardedRef) => {
    const { __scopeAlertDialog, children, ...contentProps } = props;
    const dialogScope = useDialogScope(__scopeAlertDialog);
    const contentRef = reactExports.useRef(null);
    const composedRefs = useComposedRefs(forwardedRef, contentRef);
    const cancelRef = reactExports.useRef(null);
    return /* @__PURE__ */ jsxRuntimeExports.jsx(
      WarningProvider,
      {
        contentName: CONTENT_NAME,
        titleName: TITLE_NAME,
        docsSlug: "alert-dialog",
        children: /* @__PURE__ */ jsxRuntimeExports.jsx(AlertDialogContentProvider, { scope: __scopeAlertDialog, cancelRef, children: /* @__PURE__ */ jsxRuntimeExports.jsxs(
          Content,
          {
            role: "alertdialog",
            ...dialogScope,
            ...contentProps,
            ref: composedRefs,
            onOpenAutoFocus: composeEventHandlers(contentProps.onOpenAutoFocus, (event) => {
              event.preventDefault();
              cancelRef.current?.focus({ preventScroll: true });
            }),
            onPointerDownOutside: (event) => event.preventDefault(),
            onInteractOutside: (event) => event.preventDefault(),
            children: [
              /* @__PURE__ */ jsxRuntimeExports.jsx(Slottable, { children }),
              /* @__PURE__ */ jsxRuntimeExports.jsx(DescriptionWarning, { contentRef })
            ]
          }
        ) })
      }
    );
  }
);
AlertDialogContent$1.displayName = CONTENT_NAME;
var TITLE_NAME = "AlertDialogTitle";
var AlertDialogTitle$1 = reactExports.forwardRef(
  (props, forwardedRef) => {
    const { __scopeAlertDialog, ...titleProps } = props;
    const dialogScope = useDialogScope(__scopeAlertDialog);
    return /* @__PURE__ */ jsxRuntimeExports.jsx(Title, { ...dialogScope, ...titleProps, ref: forwardedRef });
  }
);
AlertDialogTitle$1.displayName = TITLE_NAME;
var DESCRIPTION_NAME = "AlertDialogDescription";
var AlertDialogDescription$1 = reactExports.forwardRef((props, forwardedRef) => {
  const { __scopeAlertDialog, ...descriptionProps } = props;
  const dialogScope = useDialogScope(__scopeAlertDialog);
  return /* @__PURE__ */ jsxRuntimeExports.jsx(Description, { ...dialogScope, ...descriptionProps, ref: forwardedRef });
});
AlertDialogDescription$1.displayName = DESCRIPTION_NAME;
var ACTION_NAME = "AlertDialogAction";
var AlertDialogAction$1 = reactExports.forwardRef(
  (props, forwardedRef) => {
    const { __scopeAlertDialog, ...actionProps } = props;
    const dialogScope = useDialogScope(__scopeAlertDialog);
    return /* @__PURE__ */ jsxRuntimeExports.jsx(Close, { ...dialogScope, ...actionProps, ref: forwardedRef });
  }
);
AlertDialogAction$1.displayName = ACTION_NAME;
var CANCEL_NAME = "AlertDialogCancel";
var AlertDialogCancel$1 = reactExports.forwardRef(
  (props, forwardedRef) => {
    const { __scopeAlertDialog, ...cancelProps } = props;
    const { cancelRef } = useAlertDialogContentContext(CANCEL_NAME, __scopeAlertDialog);
    const dialogScope = useDialogScope(__scopeAlertDialog);
    const ref = useComposedRefs(forwardedRef, cancelRef);
    return /* @__PURE__ */ jsxRuntimeExports.jsx(Close, { ...dialogScope, ...cancelProps, ref });
  }
);
AlertDialogCancel$1.displayName = CANCEL_NAME;
var DescriptionWarning = ({ contentRef }) => {
  const MESSAGE = `\`${CONTENT_NAME}\` requires a description for the component to be accessible for screen reader users.

You can add a description to the \`${CONTENT_NAME}\` by passing a \`${DESCRIPTION_NAME}\` component as a child, which also benefits sighted users by adding visible context to the dialog.

Alternatively, you can use your own component as a description by assigning it an \`id\` and passing the same value to the \`aria-describedby\` prop in \`${CONTENT_NAME}\`. If the description is confusing or duplicative for sighted users, you can use the \`@radix-ui/react-visually-hidden\` primitive as a wrapper around your description component.

For more information, see https://radix-ui.com/primitives/docs/components/alert-dialog`;
  reactExports.useEffect(() => {
    const hasDescription = document.getElementById(
      contentRef.current?.getAttribute("aria-describedby")
    );
    if (!hasDescription) console.warn(MESSAGE);
  }, [MESSAGE, contentRef]);
  return null;
};
var Root2 = AlertDialog$1;
var Trigger2 = AlertDialogTrigger$1;
var Overlay2 = AlertDialogOverlay$1;
var Content2 = AlertDialogContent$1;
var Action = AlertDialogAction$1;
var Cancel = AlertDialogCancel$1;
var Title2 = AlertDialogTitle$1;
var Description2 = AlertDialogDescription$1;
function AlertDialog({ ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(Root2, { "data-slot": "alert-dialog", ...props });
}
function AlertDialogTrigger({ ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(Trigger2, { "data-slot": "alert-dialog-trigger", ...props });
}
function AlertDialogOverlay({ className, ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(
    Overlay2,
    {
      "data-slot": "alert-dialog-overlay",
      className: cn(
        "data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/50",
        className
      ),
      ...props
    }
  );
}
function AlertDialogContent({ className, ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsxs(jsxRuntimeExports.Fragment, { children: [
    /* @__PURE__ */ jsxRuntimeExports.jsx(AlertDialogOverlay, {}),
    /* @__PURE__ */ jsxRuntimeExports.jsx(
      Content2,
      {
        "data-slot": "alert-dialog-content",
        className: cn(
          "bg-background data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 fixed top-[50%] left-[50%] z-50 grid w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] gap-4 rounded-lg border p-6 shadow-lg duration-200 sm:max-w-lg",
          className
        ),
        ...props
      }
    )
  ] });
}
function AlertDialogHeader({ className, ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(
    "div",
    {
      "data-slot": "alert-dialog-header",
      className: cn("flex flex-col gap-2 text-center sm:text-left", className),
      ...props
    }
  );
}
function AlertDialogFooter({ className, ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(
    "div",
    {
      "data-slot": "alert-dialog-footer",
      className: cn("flex flex-col-reverse gap-2 sm:flex-row sm:justify-end", className),
      ...props
    }
  );
}
function AlertDialogTitle({ className, ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(
    Title2,
    {
      "data-slot": "alert-dialog-title",
      className: cn("text-lg font-semibold", className),
      ...props
    }
  );
}
function AlertDialogDescription({
  className,
  ...props
}) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(
    Description2,
    {
      "data-slot": "alert-dialog-description",
      className: cn("text-muted-foreground text-sm", className),
      ...props
    }
  );
}
function AlertDialogAction({ className, ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(Action, { className: cn(buttonVariants(), className), ...props });
}
function AlertDialogCancel({ className, ...props }) {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(Cancel, { className: cn(buttonVariants({ variant: "outline" }), className), ...props });
}
const UnsavedChangesDialog = ({ open, onStay, onDiscard }) => {
  return /* @__PURE__ */ jsxRuntimeExports.jsx(AlertDialog, { open, children: /* @__PURE__ */ jsxRuntimeExports.jsxs(AlertDialogContent, { children: [
    /* @__PURE__ */ jsxRuntimeExports.jsxs(AlertDialogHeader, { children: [
      /* @__PURE__ */ jsxRuntimeExports.jsx(AlertDialogTitle, { children: __("Leave without saving?", "wp-sms") }),
      /* @__PURE__ */ jsxRuntimeExports.jsx(AlertDialogDescription, { children: __(
        "You have unsaved changes on this page. If you leave now, your changes will be lost.",
        "wp-sms"
      ) })
    ] }),
    /* @__PURE__ */ jsxRuntimeExports.jsxs(AlertDialogFooter, { children: [
      /* @__PURE__ */ jsxRuntimeExports.jsx(AlertDialogCancel, { onClick: onDiscard, children: __("Discard and leave", "wp-sms") }),
      /* @__PURE__ */ jsxRuntimeExports.jsx(AlertDialogAction, { onClick: onStay, children: __("Stay on page", "wp-sms") })
    ] })
  ] }) });
};
const SchemaForm = ({ formSchema, defaultValues, onSubmit, onFieldAction }) => {
  const [showDialog, setShowDialog] = reactExports.useState(false);
  const form = useAppForm({
    defaultValues,
    onSubmit: async () => {
      const dirtyValues = getDirtyFormValues(form, formSchema);
      if (Object.keys(dirtyValues).length === 0) {
        return;
      }
      await onSubmit(dirtyValues);
    }
  });
  const { proceed, reset, status } = useBlocker({
    shouldBlockFn: () => form.state.isDirty,
    enableBeforeUnload: form.state.isDirty,
    withResolver: true
  });
  reactExports.useEffect(() => {
    if (status === "blocked") {
      setShowDialog(true);
    }
  }, [status]);
  const handleStay = () => {
    setShowDialog(false);
    reset();
  };
  const handleDiscard = () => {
    setShowDialog(false);
    proceed();
  };
  reactExports.useEffect(() => {
    form.reset(defaultValues);
  }, [defaultValues, form]);
  if (!formSchema) {
    return /* @__PURE__ */ jsxRuntimeExports.jsx("div", { className: "container mx-auto py-8", children: /* @__PURE__ */ jsxRuntimeExports.jsxs(Alert, { variant: "destructive", children: [
      /* @__PURE__ */ jsxRuntimeExports.jsx(CircleAlert, { className: "h-4 w-4" }),
      /* @__PURE__ */ jsxRuntimeExports.jsx(AlertDescription, { children: __("No schema data available.", "wp-sms") })
    ] }) });
  }
  return /* @__PURE__ */ jsxRuntimeExports.jsxs(jsxRuntimeExports.Fragment, { children: [
    /* @__PURE__ */ jsxRuntimeExports.jsxs(
      "form",
      {
        onSubmit: (e) => {
          e.preventDefault();
          e.stopPropagation();
          form.handleSubmit();
        },
        className: "flex flex-col gap-y-6",
        children: [
          /* @__PURE__ */ jsxRuntimeExports.jsx(GroupTitle, { label: formSchema.label || "" }),
          formSchema.sections.map((section, index) => /* @__PURE__ */ jsxRuntimeExports.jsxs(Card, { className: "flex flex-col gap-y-8", children: [
            /* @__PURE__ */ jsxRuntimeExports.jsxs(CardHeader, { children: [
              /* @__PURE__ */ jsxRuntimeExports.jsxs(CardTitle, { children: [
                section.title,
                section.tag && /* @__PURE__ */ jsxRuntimeExports.jsx(TagBadge, { className: "ms-2", tag: section.tag })
              ] }),
              section.subtitle && /* @__PURE__ */ jsxRuntimeExports.jsx(CardDescription, { children: section.subtitle })
            ] }),
            /* @__PURE__ */ jsxRuntimeExports.jsx(CardContent, { className: "flex flex-col gap-y-8", children: section.fields?.map((field) => /* @__PURE__ */ jsxRuntimeExports.jsx(
              FieldRenderer,
              {
                form,
                schema: field,
                onOpenSubFields: onFieldAction,
                onSubmit
              },
              field.key
            )) })
          ] }, `${section?.id}-${index}`)),
          /* @__PURE__ */ jsxRuntimeExports.jsx(form.AppForm, { children: /* @__PURE__ */ jsxRuntimeExports.jsx(form.FormActions, {}) })
        ]
      }
    ),
    /* @__PURE__ */ jsxRuntimeExports.jsx(UnsavedChangesDialog, { open: showDialog, onStay: handleStay, onDiscard: handleDiscard })
  ] });
};
function useInvalidateQuery(queryKey) {
  const [isInvalidating, setIsInvalidating] = reactExports.useState(false);
  const queryClient = useQueryClient();
  const invalidateQuery = reactExports.useCallback(async () => {
    setIsInvalidating(true);
    try {
      await queryClient.invalidateQueries({
        queryKey,
        type: "all"
      });
    } catch (error) {
    } finally {
      setIsInvalidating(false);
    }
  }, [queryKey, queryClient]);
  return {
    invalidateQuery,
    // Function to trigger query invalidation
    isInvalidating
    // Boolean indicating if invalidation is in progress
  };
}
function useSaveSettingsValues(params) {
  const { invalidateQuery: invalidateGetSchemaByGroup } = useInvalidateQuery(
    getSchemaByGroup({
      groupName: params.groupName || "general",
      include_hidden: params.include_hidden
    }).queryKey
  );
  const { invalidateQuery: invalidateGetSettingsValuesByGroup } = useInvalidateQuery(
    getSettingsValuesByGroup({
      groupName: params.groupName || "general"
    }).queryKey
  );
  return useMutation({
    mutationFn: (body) => instance.put("/settings/save", body),
    onSuccess: async () => {
      try {
        await invalidateGetSchemaByGroup();
        await invalidateGetSettingsValuesByGroup();
        toast.success(__("Settings saved successfully", "wp-sms"));
      } catch {
        toast.info(__("Settings saved but form refresh failed", "wp-sms"));
      }
    },
    onError: () => toast.error(__("Something went wrong!", "wp-sms"))
  });
}
export {
  AlertDialog as A,
  Badge as B,
  FieldRenderer as F,
  QueryObserver as Q,
  SchemaForm as S,
  TagBadge as T,
  useQueryErrorResetBoundary as a,
  ensurePreventErrorBoundaryRetry as b,
  useClearResetErrorBoundary as c,
  defaultThrowOnError as d,
  ensureSuspenseTimers as e,
  fetchOptimistic as f,
  getHasError as g,
  useSaveSettingsValues as h,
  useAppForm as i,
  useFieldContext as j,
  useStore as k,
  AlertDialogTrigger as l,
  AlertDialogContent as m,
  AlertDialogHeader as n,
  AlertDialogTitle as o,
  AlertDialogDescription as p,
  AlertDialogFooter as q,
  AlertDialogCancel as r,
  shouldSuspend as s,
  AlertDialogAction as t,
  useIsRestoring as u,
  shouldShowField as v,
  willFetch as w
};
