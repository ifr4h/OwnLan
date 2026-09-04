import type { CachedLesson, CachedPupil, CachedToday, OutboxItem } from './types'

const DB_NAME = 'ownlane-offline'
const DB_VERSION = 1

type StoreName = 'meta' | 'today' | 'lessons' | 'pupils' | 'outbox'

function openDb(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    if (typeof indexedDB === 'undefined') {
      reject(new Error('IndexedDB is not available on this device.'))
      return
    }
    const request = indexedDB.open(DB_NAME, DB_VERSION)
    request.onerror = () => reject(request.error ?? new Error('Could not open offline storage.'))
    request.onsuccess = () => resolve(request.result)
    request.onupgradeneeded = () => {
      const db = request.result
      if (!db.objectStoreNames.contains('meta')) {
        db.createObjectStore('meta')
      }
      if (!db.objectStoreNames.contains('today')) {
        db.createObjectStore('today', { keyPath: 'scopeKey' })
      }
      if (!db.objectStoreNames.contains('lessons')) {
        const lessons = db.createObjectStore('lessons', { keyPath: ['scopeKey', 'id'] })
        lessons.createIndex('byScope', 'scopeKey', { unique: false })
      }
      if (!db.objectStoreNames.contains('pupils')) {
        const pupils = db.createObjectStore('pupils', { keyPath: ['scopeKey', 'id'] })
        pupils.createIndex('byScope', 'scopeKey', { unique: false })
      }
      if (!db.objectStoreNames.contains('outbox')) {
        const outbox = db.createObjectStore('outbox', { keyPath: 'id' })
        outbox.createIndex('byScope', 'scopeKey', { unique: false })
        outbox.createIndex('byStatus', 'status', { unique: false })
      }
    }
  })
}

function req<T>(request: IDBRequest<T>): Promise<T> {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error ?? new Error('Offline storage request failed.'))
  })
}

async function withStore<T>(
  storeName: StoreName,
  mode: IDBTransactionMode,
  fn: (store: IDBObjectStore) => Promise<T> | T,
): Promise<T> {
  const db = await openDb()
  try {
    const tx = db.transaction(storeName, mode)
    const store = tx.objectStore(storeName)
    const result = await fn(store)
    await new Promise<void>((resolve, reject) => {
      tx.oncomplete = () => resolve()
      tx.onerror = () => reject(tx.error ?? new Error('Offline storage transaction failed.'))
      tx.onabort = () => reject(tx.error ?? new Error('Offline storage transaction aborted.'))
    })
    return result
  } finally {
    db.close()
  }
}

export async function setMeta(key: string, value: unknown): Promise<void> {
  await withStore('meta', 'readwrite', store => req(store.put(value, key)))
}

export async function getMeta<T>(key: string): Promise<T | undefined> {
  return await withStore('meta', 'readonly', store => req(store.get(key) as IDBRequest<T>))
}

export async function putToday(entry: CachedToday): Promise<void> {
  await withStore('today', 'readwrite', store => req(store.put(entry)))
}

export async function getToday(scopeKey: string): Promise<CachedToday | undefined> {
  return await withStore('today', 'readonly', store =>
    req(store.get(scopeKey) as IDBRequest<CachedToday | undefined>),
  )
}

export async function putLesson(entry: CachedLesson): Promise<void> {
  await withStore('lessons', 'readwrite', store => req(store.put(entry)))
}

export async function getLesson(
  scopeKey: string,
  id: number,
): Promise<CachedLesson | undefined> {
  return await withStore('lessons', 'readonly', store =>
    req(store.get([scopeKey, id]) as IDBRequest<CachedLesson | undefined>),
  )
}

export async function putPupil(entry: CachedPupil): Promise<void> {
  await withStore('pupils', 'readwrite', store => req(store.put(entry)))
}

export async function getPupil(
  scopeKey: string,
  id: number,
): Promise<CachedPupil | undefined> {
  return await withStore('pupils', 'readonly', store =>
    req(store.get([scopeKey, id]) as IDBRequest<CachedPupil | undefined>),
  )
}

export async function putOutboxItem(item: OutboxItem): Promise<void> {
  await withStore('outbox', 'readwrite', store => req(store.put(item)))
}

export async function getOutboxItem(id: string): Promise<OutboxItem | undefined> {
  return await withStore('outbox', 'readonly', store =>
    req(store.get(id) as IDBRequest<OutboxItem | undefined>),
  )
}

export async function deleteOutboxItem(id: string): Promise<void> {
  await withStore('outbox', 'readwrite', store => req(store.delete(id)))
}

export async function listOutbox(scopeKey: string): Promise<OutboxItem[]> {
  return await withStore('outbox', 'readonly', async (store) => {
    const index = store.index('byScope')
    const rows = await req(index.getAll(scopeKey) as IDBRequest<OutboxItem[]>)
    return rows.sort((a, b) => a.createdAt.localeCompare(b.createdAt))
  })
}

export async function countPendingOutbox(scopeKey: string): Promise<number> {
  const items = await listOutbox(scopeKey)
  return items.filter(i => i.status === 'pending' || i.status === 'failed' || i.status === 'syncing').length
}

/** Wipe all operational data for one signed-in organisation scope. */
export async function clearScope(scopeKey: string): Promise<void> {
  const db = await openDb()
  try {
    const tx = db.transaction(['today', 'lessons', 'pupils', 'outbox', 'meta'], 'readwrite')
    tx.objectStore('today').delete(scopeKey)

    await clearIndex(tx.objectStore('lessons').index('byScope'), scopeKey, tx.objectStore('lessons'))
    await clearIndex(tx.objectStore('pupils').index('byScope'), scopeKey, tx.objectStore('pupils'))
    await clearIndex(tx.objectStore('outbox').index('byScope'), scopeKey, tx.objectStore('outbox'))

    await new Promise<void>((resolve, reject) => {
      tx.oncomplete = () => resolve()
      tx.onerror = () => reject(tx.error ?? new Error('Failed clearing offline data.'))
    })
  } finally {
    db.close()
  }
}

async function clearIndex(
  index: IDBIndex,
  scopeKey: string,
  store: IDBObjectStore,
): Promise<void> {
  const keys = await req(index.getAllKeys(scopeKey))
  for (const key of keys) {
    await req(store.delete(key))
  }
}
