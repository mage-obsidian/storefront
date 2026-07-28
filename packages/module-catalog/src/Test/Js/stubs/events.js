const observers = {};

export const events = {
    observe(event, observer) {
        const list = (observers[event] ??= []);
        list.push(observer);

        return () => {
            const at = list.indexOf(observer);
            if (at > -1) {
                list.splice(at, 1);
            }
        };
    },

    async dispatch(event, data) {
        for (const observer of (observers[event] ?? []).slice()) {
            await observer(data);
        }

        return data;
    },

    recorded(event) {
        return dispatched[event] ?? [];
    },

    reset() {
        Object.keys(observers).forEach((key) => delete observers[key]);
        Object.keys(dispatched).forEach((key) => delete dispatched[key]);
    },
};

const dispatched = {};
const rawDispatch = events.dispatch.bind(events);
events.dispatch = async (event, data) => {
    (dispatched[event] ??= []).push(data);

    return rawDispatch(event, data);
};

export default events;
